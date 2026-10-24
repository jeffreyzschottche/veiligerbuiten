import { Capacitor } from '@capacitor/core';
import { BackgroundRunner } from '@capacitor/background-runner';
import type { Incident, Place } from '~/types/Incident';
import { appCopy } from '~/locales/app';
import { storage } from '~/utils/storage';
import { distanceMeters } from '~/composables/useGeo';
import type { Position } from '~/composables/useDevice';

const RUNNER = 'nl.veiligbuiten.app.check';
const CACHE_KEY = 'vb_sync';
/** Met de app open vernieuwen we vaker dan op de achtergrond, maar nooit vaker dan dit. */
const FOREGROUND_REFRESH_MS = 10 * 60 * 1000;
const MANUAL_REFRESH_MS = 60 * 1000;
const RESYNC_DISTANCE_M = 10_000;

interface SyncCache {
  at: number;
  center: { latitude: number; longitude: number };
  items: Incident[];
}

/**
 * Lokale kopie van alles binnen 30 km. De kaart leest hieruit; de server
 * zien we pas weer als de kopie oud is of je ver bent verplaatst.
 */
export function useSync() {
  const incidents = useState<Incident[]>('sync-incidents', () => []);
  const syncedAt = useState<number | null>('sync-at', () => null);
  const center = useState<SyncCache['center'] | null>('sync-center', () => null);
  const status = useState<'idle' | 'loading' | 'offline' | 'error'>('sync-status', () => 'idle');
  const lastManual = useState<number>('sync-manual', () => 0);

  async function restore() {
    const cache = await storage.get<SyncCache>(CACHE_KEY);
    if (!cache) return;
    incidents.value = cache.items.filter((item) => !item.expiresAt || new Date(item.expiresAt).getTime() > Date.now());
    syncedAt.value = cache.at;
    center.value = cache.center;
  }

  function isStale(position: Position, maxAge = FOREGROUND_REFRESH_MS) {
    if (!syncedAt.value || !center.value) return true;
    return Date.now() - syncedAt.value > maxAge || distanceMeters(position.latitude, position.longitude, center.value.latitude, center.value.longitude) > RESYNC_DISTANCE_M;
  }

  async function sync(position: Position, { manual = false, force = false } = {}) {
    if (force) {
      // Taalwissel: titels komen vertaald van de server, dus de cache moet opnieuw.
    } else if (manual) {
      if (Date.now() - lastManual.value < MANUAL_REFRESH_MS && !isStale(position, MANUAL_REFRESH_MS)) return;
      lastManual.value = Date.now();
    } else if (!isStale(position)) {
      return;
    }
    status.value = 'loading';
    try {
      // Afgerond op ~100 m: genoeg voor 30 km, en de server ziet geen exacte positie.
      const query = `latitude=${position.latitude.toFixed(3)}&longitude=${position.longitude.toFixed(3)}`;
      const response = await useApi().get<{ data: Incident[]; center: SyncCache['center'] }>(`/incidents/sync?${query}`);
      const cache: SyncCache = { at: Date.now(), center: response.center, items: response.data };
      incidents.value = cache.items;
      syncedAt.value = cache.at;
      center.value = cache.center;
      status.value = 'idle';
      await storage.set(CACHE_KEY, cache);
      await toRunner('sync', { at: cache.at, center: cache.center, items: cache.items });
    } catch (error: any) {
      status.value = error?.status ? 'error' : 'offline';
      if (error?.status === 402) await useAuthStore().refreshAccount().catch(() => undefined);
    }
  }

  /** Eén incident vervangen na een stem, zonder hele sync. */
  function replace(incident: Incident) {
    incidents.value = incidents.value.map((item) => item.id === incident.id ? incident : item);
    if (!incidents.value.some((item) => item.id === incident.id)) incidents.value = [incident, ...incidents.value];
    storage.set(CACHE_KEY, { at: syncedAt.value ?? Date.now(), center: center.value, items: incidents.value });
  }

  async function clear() {
    incidents.value = [];
    syncedAt.value = null;
    center.value = null;
    await storage.remove(CACHE_KEY);
    await toRunner('configure', {});
  }

  return { incidents, syncedAt, status, restore, sync, isStale, replace, clear };
}

/** Geeft account, instellingen en plekken door aan de achtergrondcontrole. */
export async function configureRunner(places: Place[]) {
  const auth = useAuthStore();
  const account = auth.account;
  if (!auth.token || !account?.subscription.active) return toRunner('configure', {});
  const { locale } = useLocale();
  const copy = appCopy[locale.value];
  const config = useRuntimeConfig();
  const apiBase = String(config.public.apiBaseUrl);

  await toRunner('configure', {
    apiBase: apiBase.startsWith('http') ? apiBase : `${window.location.origin}${apiBase}`,
    token: auth.token,
    locale: locale.value,
    interval: account.settings.check_interval_minutes,
    minLevel: account.settings.alert_filters.min_level,
    categories: account.settings.alert_filters.categories,
    quiet: account.settings.quiet_hours,
    places: places.map(({ latitude, longitude, radius_m, alerts_enabled }) => ({ latitude, longitude, radius_m, alerts_enabled })),
    strings: { near: copy.notify.near, bundle: copy.notify.bundle },
  });
}

async function toRunner(event: string, details: Record<string, unknown>) {
  if (!Capacitor.isNativePlatform()) return;
  await BackgroundRunner.dispatchEvent({ label: RUNNER, event, details }).catch(() => undefined);
}
