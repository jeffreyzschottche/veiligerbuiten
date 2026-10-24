import { Capacitor } from '@capacitor/core';
import { BackgroundRunner } from '@capacitor/background-runner';
import type { Hotspot, Incident, Place } from '~/types/Incident';
import { appCopy } from '~/locales/app';
import { storage } from '~/utils/storage';
import { distanceMeters } from '~/composables/useGeo';
import type { Position } from '~/composables/useDevice';

const RUNNER = 'nl.veiligerbuiten.app.check';
const CACHE_KEY = 'vb_sync';
/** Snapshots are bounded even when an older report has no individual expiry. */
const MAX_CACHE_AGE_MS = 6 * 60 * 60 * 1000;
const CACHE_RADIUS_M = 30_000;
const MANUAL_REFRESH_MS = 60 * 1000;
const RESYNC_DISTANCE_M = 10_000;
/** Zelfde raster als de server: wie in dezelfde buurt is, vraagt hetzelfde gebied (gedeelde servercache). */
const SNAP_DEGREES = 0.05;
/** Niet alle telefoons tegelijk op het hele uur: elke sessie wijkt een beetje af (±10%). */
const JITTER = 0.9 + Math.random() * 0.2;

interface SyncCache {
  at: number;
  center: { latitude: number; longitude: number };
  items: Incident[];
  hotspots?: Hotspot[];
  /** Servertijd van de vorige sync; met 'since' stuurt de server alleen wijzigingen. */
  cursor?: number | null;
  etag?: string | null;
  /** Ondergrens die de server meegeeft als het druk is. */
  minSyncAfter?: number | null;
}

interface SyncResponse {
  mode: 'full' | 'delta';
  data?: Incident[];
  changes?: Incident[];
  removed?: number[];
  hotspots?: Hotspot[];
  center: SyncCache['center'];
  cursor: number;
  min_sync_after?: number;
}

function snap(position: Position) {
  const round = (value: number) => Math.round(Math.round(value / SNAP_DEGREES) * SNAP_DEGREES * 10_000) / 10_000;
  return { latitude: round(position.latitude), longitude: round(position.longitude) };
}

/**
 * Lokale kopie van alles binnen 30 km. De kaart leest hieruit; de server
 * zien we pas weer als de kopie oud is of je ver bent verplaatst.
 */
export function useSync() {
  const incidents = useState<Incident[]>('sync-incidents', () => []);
  const hotspots = useState<Hotspot[]>('sync-hotspots', () => []);
  const meta = useState<Pick<SyncCache, 'cursor' | 'etag' | 'minSyncAfter'>>('sync-meta', () => ({}));
  const syncedAt = useState<number | null>('sync-at', () => null);
  const center = useState<SyncCache['center'] | null>('sync-center', () => null);
  const status = useState<'idle' | 'loading' | 'offline' | 'error'>('sync-status', () => 'idle');
  const lastManual = useState<number>('sync-manual', () => 0);
  const lastAttempt = useState<number>('sync-last-attempt', () => 0);

  function refreshInterval() {
    const minutes = useAuthStore().account?.settings.check_interval_minutes ?? 15;
    const chosen = ([15, 30, 60].includes(minutes) ? minutes : 60) * 60_000;
    return Math.max(chosen, (meta.value.minSyncAfter ?? 0) * 1000) * JITTER;
  }

  function snapshot(): SyncCache | null {
    if (!center.value) return null;
    return { at: syncedAt.value ?? Date.now(), center: center.value, items: incidents.value, hotspots: hotspots.value, ...meta.value };
  }

  function pruneExpired() {
    const now = Date.now();
    incidents.value = incidents.value.filter((item) =>
      item.status !== 'opgelost' && item.status !== 'verlopen' &&
      (!item.expiresAt || Date.parse(item.expiresAt) > now) &&
      !!syncedAt.value && now >= syncedAt.value && now - syncedAt.value <= MAX_CACHE_AGE_MS,
    );
  }

  function covers(position: Position) {
    return !!syncedAt.value && !!center.value && Date.now() >= syncedAt.value && Date.now() - syncedAt.value <= MAX_CACHE_AGE_MS &&
      distanceMeters(position.latitude, position.longitude, center.value.latitude, center.value.longitude) <= CACHE_RADIUS_M;
  }

  async function restore() {
    const cache = await storage.get<SyncCache>(CACHE_KEY);
    if (!cache) return;
    incidents.value = cache.items;
    hotspots.value = cache.hotspots ?? [];
    meta.value = { cursor: cache.cursor ?? null, etag: cache.etag ?? null, minSyncAfter: cache.minSyncAfter ?? null };
    syncedAt.value = cache.at;
    center.value = cache.center;
    pruneExpired();
    await toRunner('sync', { ...cache, items: incidents.value });
  }

  function isStale(position: Position, maxAge = refreshInterval()) {
    if (!syncedAt.value || !center.value) return true;
    return Date.now() - syncedAt.value > maxAge || distanceMeters(position.latitude, position.longitude, center.value.latitude, center.value.longitude) > RESYNC_DISTANCE_M;
  }

  async function sync(position: Position, { manual = false, force = false } = {}) {
    pruneExpired();
    if (status.value === 'loading') return;
    if (import.meta.client && !navigator.onLine) { status.value = 'offline'; return; }
    if (!force && !manual && status.value === 'offline' && Date.now() - lastAttempt.value < 60_000) return;
    if (force) {
      // Taalwissel: titels komen vertaald van de server, dus de cache moet opnieuw.
    } else if (manual) {
      if (Date.now() - lastManual.value < MANUAL_REFRESH_MS && !isStale(position, MANUAL_REFRESH_MS)) return;
      lastManual.value = Date.now();
    } else if (!isStale(position)) {
      return;
    }
    status.value = 'loading';
    lastAttempt.value = Date.now();
    let timeout: ReturnType<typeof setTimeout> | undefined;
    try {
      // Op het raster van ~5 km: de server ziet geen exacte positie en kan het antwoord delen met buren.
      const area = snap(position);
      const previous = snapshot();
      // Zelfde vak en niet te oud: vraag alleen wat er veranderde (of een lege 304).
      const incremental = !force && !!previous?.cursor && !!previous.etag && Date.now() - previous.at < MAX_CACHE_AGE_MS &&
        previous.center.latitude === area.latitude && previous.center.longitude === area.longitude;
      const query = `latitude=${area.latitude}&longitude=${area.longitude}${incremental ? `&since=${previous!.cursor}` : ''}`;
      const response = await Promise.race([
        useApi().getRaw<SyncResponse>(`/incidents/sync?${query}`, incremental ? { 'If-None-Match': previous!.etag! } : {}),
        new Promise<never>((_, reject) => { timeout = setTimeout(() => reject(new Error('sync_timeout')), 8000); }),
      ]);
      const cache = merge(previous, response.data, response.headers.etag ?? null, incremental);
      if (!cache) throw new Error('sync_invalid');
      incidents.value = cache.items;
      hotspots.value = cache.hotspots ?? [];
      meta.value = { cursor: cache.cursor, etag: cache.etag, minSyncAfter: cache.minSyncAfter };
      syncedAt.value = cache.at;
      center.value = cache.center;
      status.value = 'idle';
      pruneExpired();
      await storage.set(CACHE_KEY, cache);
      await toRunner('sync', { ...cache });
    } catch (error: any) {
      status.value = !error?.status || error.status >= 500 ? 'offline' : 'error';
      if (error?.status === 402) await useAuthStore().refreshAccount().catch(() => undefined);
    } finally {
      clearTimeout(timeout);
    }
  }

  /** 304: de bewaarde lijst is weer actueel. Delta: voortbouwen op de vorige. Volledig: vervangen. */
  function merge(previous: SyncCache | null, body: SyncResponse | null, etag: string | null, incremental: boolean): SyncCache | null {
    if (!body) return previous ? { ...previous, at: Date.now() } : null;
    let items: Incident[];
    if (body.mode === 'delta') {
      if (!incremental || !previous || !Array.isArray(body.changes)) return null;
      const replaced = new Set([...(body.removed ?? []), ...body.changes.map((item) => item.id)]);
      items = [...body.changes, ...previous.items.filter((item) => !replaced.has(item.id))];
    } else {
      if (!Array.isArray(body.data)) return null;
      items = body.data;
    }
    return { at: Date.now(), center: body.center, items, hotspots: body.hotspots ?? previous?.hotspots ?? [], cursor: body.cursor, etag, minSyncAfter: body.min_sync_after ?? null };
  }

  /** Eén incident vervangen na een stem, zonder hele sync. */
  function replace(incident: Incident) {
    incidents.value = incidents.value.map((item) => item.id === incident.id ? incident : item);
    if (!incidents.value.some((item) => item.id === incident.id)) incidents.value = [incident, ...incidents.value];
    pruneExpired();
    const cache = snapshot();
    if (!cache) return;
    void storage.set(CACHE_KEY, cache);
    void toRunner('sync', { ...cache });
  }

  async function checkNearby(position: Position) {
    pruneExpired();
    if (!useAuthStore().account?.subscription.active) return;
    await toRunner('checkIn', { force: true, localOnly: true, offline: status.value === 'offline', position });
  }

  async function clear() {
    incidents.value = [];
    hotspots.value = [];
    meta.value = {};
    syncedAt.value = null;
    center.value = null;
    await storage.remove(CACHE_KEY);
    await toRunner('configure', {});
  }

  return { incidents, hotspots, syncedAt, status, restore, sync, isStale, covers, pruneExpired, checkNearby, replace, clear };
}

/** Geeft account, instellingen en plekken door aan de achtergrondcontrole. */
export async function configureRunner(places: Place[]) {
  const auth = useAuthStore();
  const account = auth.account;
  if (!auth.token || !account?.subscription.active) return toRunner('configure', {});
  const { locale } = useLocale();
  const copy = appCopy[locale.value];
  const config = useRuntimeConfig();
  const apiBase = String(config.public.apiBase);

  await toRunner('configure', {
    apiBase: apiBase.startsWith('http') ? apiBase : `${window.location.origin}${apiBase}`,
    token: auth.token,
    validUntil: account.subscription.expires_at,
    locale: locale.value,
    interval: account.settings.check_interval_minutes,
    minLevel: account.settings.alert_filters.min_level,
    categories: account.settings.alert_filters.categories,
    quiet: account.settings.quiet_hours,
    places: places.map(({ latitude, longitude, radius_m, alerts_enabled }) => ({ latitude, longitude, radius_m, alerts_enabled })),
    strings: {
      near: copy.notify.near, offlineNear: copy.notify.offlineNear, offlineLabel: copy.notify.offlineLabel, bundle: copy.notify.bundle,
      hotspotTitles: copy.hotspots.titles, hotspotBody: copy.hotspots.body,
    },
  });
}

let runnerQueue = Promise.resolve();
async function toRunner(event: string, details: Record<string, unknown>) {
  if (!Capacitor.isNativePlatform()) return;
  runnerQueue = runnerQueue.then(async () => {
    await BackgroundRunner.dispatchEvent({ label: RUNNER, event, details }).catch(() => undefined);
  });
  await runnerQueue;
}
