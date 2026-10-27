<template>
  <div class="vb-app" :style="{ '--ad-h': ads.visible.value && ads.height.value ? `${ads.height.value + 6}px` : '0px' }">
    <AppMap
      ref="map"
      :incidents="sync.incidents.value"
      :hotspots="sync.hotspots.value"
      :places="places.places.value"
      :me="me"
      :selected-id="selectedId"
      :bottom-inset="panelInset"
      @select="select"
    />

    <header class="topbar">
      <div class="topbar__brand">
        <LogoMark :size="26" />
        <span class="topbar__state" :class="`is-${connection}`" aria-hidden="true" />
        <span>{{ statusText }}</span>
      </div>
      <button class="icon-btn" :aria-label="t.map.locate" @click="locate(true)"><Icon name="locate" :size="21" /></button>
    </header>

    <div v-show="tab === 'map' && !selected" ref="panelWrap">
      <NearbyPanel v-model:expanded="panelOpen" :items="nearby" :hotspots="nearbyHotspots" :has-location="!!me" :cached="cachedView" :status="statusText" @select="select" @hotspot="showHotspot" />
      <button class="report-fab" :style="{ bottom: `${panelInset + 10}px` }" @click="reportOpen = true">
        <Icon name="plus" :size="20" :stroke="2.4" />{{ t.map.report }}
      </button>
      <button v-if="!me && locationDenied" class="locate-cta btn btn--brand" :style="{ bottom: `${panelInset + 10}px` }" @click="locate(true)">{{ t.map.allowLocation }}</button>
    </div>

    <IncidentSheet :incident="selected" :meters="selectedMeters" :voted="!!selected && !!votes[selected.id]" :voting="voting" @close="selectedId = null" @vote="vote" />
    <ReportSheet :open="reportOpen" :map-center="mapCenter()" @close="reportOpen = false" @placed="placed" />

    <PlacesScreen v-if="tab === 'places'" :map-center="me ?? mapCenter()" :incidents="sync.incidents.value" />
    <AccountScreen v-if="tab === 'account'" @logout="logout" @deleted="logout" />

    <AppTabBar v-model="tab" />

    <Paywall v-if="needsSubscription" @subscribed="onSubscribed" @logout="logout" />
    <Transition name="fade"><p v-if="toast" class="toast" role="status">{{ toast }}</p></Transition>
  </div>
</template>

<script setup lang="ts">
import { App } from '@capacitor/app';
import { BackgroundRunner } from '@capacitor/background-runner';
import type { Hotspot, Incident } from '~/types/Incident';
import type { Tab } from '~/types/App';
import { distanceMeters } from '~/composables/useGeo';
import { currentPosition, watchCurrentPosition, openFromNotification, type Position } from '~/composables/useDevice';
import { storage } from '~/utils/storage';

definePageMeta({ layout: false });
useHead({ title: 'VeiligerBuiten', meta: [{ name: 'theme-color', content: '#f4f5f7' }] });

/** "In je buurt" in de lijst: binnen 5 km, of binnen het meldbereik van iets groots. */
const NEARBY_M = 5000;

/** Bekende plekken in de lijst: alleen als je er echt in de buurt bent. */
const HOTSPOT_NEARBY_M = 2000;

const route = useRoute();
const { t, fill, ago } = useAppCopy();
const auth = useAuthStore();
const device = useDevice();
const sync = useSync();
const places = usePlaces();
const ads = useAds();

const map = ref<{ flyTo: (lat: number, lng: number, zoom?: number) => void; showReach: (incident: Incident) => void; showArea: (lat: number, lng: number, meters: number) => void; center: () => Position | null } | null>(null);
const panelWrap = ref<HTMLElement | null>(null);
const tab = ref<Tab>('map');
const me = ref<Position | null>(null);
const locationDenied = ref(false);
const selectedId = ref<number | null>(null);
const panelOpen = ref(false);
const panelInset = ref(140);
const reportOpen = ref(false);
const voting = ref(false);
const votes = ref<Record<number, 'present' | 'resolved'>>({});
const toast = ref<string | null>(null);
const tick = ref(0);

const needsSubscription = computed(() => !!auth.account && !auth.account.subscription.active);
const cachedView = computed(() => {
  tick.value;
  return sync.status.value === 'offline' || sync.status.value === 'error' || !sync.syncedAt.value || (!!me.value && !sync.covers(me.value));
});
const selected = computed(() => sync.incidents.value.find((incident) => incident.id === selectedId.value) ?? null);
const selectedMeters = computed(() => selected.value && me.value ? distanceMeters(me.value.latitude, me.value.longitude, selected.value.latitude, selected.value.longitude) : null);

const nearby = computed(() => {
  const origin = me.value;
  if (!origin) return [];
  if (!sync.covers(origin)) return [];
  const rank = { danger: 3, warn: 2, info: 1 };
  return sync.incidents.value
    .map((incident) => ({ incident, distance: distanceMeters(origin.latitude, origin.longitude, incident.latitude, incident.longitude) }))
    .filter(({ incident, distance }) => distance <= Math.max(NEARBY_M, incident.alertRadius))
    .sort((a, b) => rank[b.incident.level] - rank[a.incident.level] || a.distance - b.distance);
});

const nearbyHotspots = computed(() => {
  const origin = me.value;
  if (!origin || !sync.covers(origin)) return [];
  return sync.hotspots.value
    .map((hotspot) => ({ hotspot, distance: distanceMeters(origin.latitude, origin.longitude, hotspot.latitude, hotspot.longitude) }))
    .filter(({ distance }) => distance <= HOTSPOT_NEARBY_M)
    // Wat mensen echt meldden gaat voor de buurtcijfers; daarbinnen het dichtstbij eerst.
    .sort((a, b) => Number(a.hotspot.kind === 'stats') - Number(b.hotspot.kind === 'stats') || a.distance - b.distance)
    .slice(0, 3);
});

/** Gevaar binnen het bereik van een melding: dan nooit een advertentie. */
const dangerHere = computed(() => nearby.value.some(({ incident, distance }) => incident.level === 'danger' && distance <= incident.alertRadius));
const idle = computed(() => tab.value === 'map' && !selectedId.value && !reportOpen.value && !panelOpen.value && !needsSubscription.value);
const connection = computed(() => {
  tick.value;
  if (sync.status.value === 'offline') return 'offline';
  return cachedView.value ? 'cached' : 'live';
});

const statusText = computed(() => {
  tick.value;
  if (sync.status.value === 'loading' && !sync.syncedAt.value) return '…';
  const when = sync.syncedAt.value ? ago(new Date(sync.syncedAt.value).toISOString()) : '';
  if (!sync.syncedAt.value) return t.value.map.noSnapshot;
  if (me.value && !sync.covers(me.value)) return t.value.map.outsideCache;
  return sync.status.value === 'offline' ? fill(t.value.map.offline, { time: when }) : fill(t.value.map.updated, { time: when });
});

function mapCenter() {
  return map.value?.center() ?? null;
}

async function locate(fly = false, force = false) {
  try {
    const position = await currentPosition();
    me.value = position;
    locationDenied.value = false;
    if (fly) map.value?.flyTo(position.latitude, position.longitude, 14);
    await sync.checkNearby(position);
    await sync.sync(position, { force });
    await sync.checkNearby(position);
  } catch {
    locationDenied.value = true;
    if (fly) flash(t.value.errors.location);
    // Zonder locatie synchroniseren we rond het midden van de kaart.
    const center = mapCenter();
    if (center) await sync.sync(center, { force });
  }
}

async function select(id: number | null) {
  selectedId.value = id;
  if (id === null) return;
  panelOpen.value = false;
  tab.value = 'map';
  let incident = sync.incidents.value.find((item) => item.id === id);
  if (!incident) {
    // Uit een melding of gedeelde link: los ophalen als het niet in de cache zit.
    incident = await useApi().get<{ data: Incident }>(`/incidents/${id}`).then((response) => response.data).catch(() => undefined);
    if (!incident) {
      selectedId.value = null;
      return;
    }
    sync.replace(incident);
  }
  map.value?.showReach(incident);
}

/** Direct bedankt (optimistisch), op de achtergrond verstuurd; bij een fout terugdraaien. */
async function vote(kind: 'present' | 'resolved') {
  const incident = selected.value;
  if (!incident || votes.value[incident.id]) return;
  const before = votes.value;
  votes.value = { ...votes.value, [incident.id]: kind };
  voting.value = true;
  try {
    await device.ensureInstallation();
    const { data } = await useApi().post<{ data: Incident }>(`/incidents/${incident.id}/${kind === 'present' ? 'confirm' : 'resolve'}`, {
      latitude: me.value?.latitude ?? null,
      longitude: me.value?.longitude ?? null,
    });
    sync.replace(data);
    await storage.set('vb_votes', votes.value);
  } catch (caught: any) {
    if (caught?.status === 409) await storage.set('vb_votes', votes.value);
    else {
      votes.value = before;
      flash(caught?.data?.message ?? t.value.errors.generic);
    }
  } finally {
    voting.value = false;
  }
}

function showHotspot(key: string) {
  const hotspot = sync.hotspots.value.find((item) => item.key === key);
  if (!hotspot) return;
  panelOpen.value = false;
  map.value?.showArea(hotspot.latitude, hotspot.longitude, hotspot.radius);
  if (hotspot.kind === 'stats') flash(statsSummary(hotspot), 6000);
}

/** "Leidsebuurt: 171× geweld of straatroof (o.a. 12× straatroof) in 12 maanden." */
function statsSummary(hotspot: Hotspot) {
  const copy = t.value.hotspots.stats;
  const [type, n] = Object.entries(hotspot.counts ?? {}).filter(([key]) => key !== 'mishandeling').sort((a, b) => b[1] - a[1])[0] ?? [];
  const detail = type && n ? fill(copy.detail, { n, type: copy.types[type] ?? type }) : '';
  return fill(copy.toast, { name: hotspot.name ?? '', n: hotspot.count, detail });
}

let openedFromAlert = false;
let adTimer: ReturnType<typeof setTimeout> | undefined;
/** Even rondkijken eerst; pas daarna, als alles rustig is, misschien een banner. */
function scheduleAd() {
  clearTimeout(adTimer);
  adTimer = setTimeout(() => {
    void ads.maybeShow({ calm: !dangerHere.value, idle: idle.value, fromNotification: openedFromAlert });
    openedFromAlert = false;
  }, 6000);
}

function placed(incident: Incident) {
  sync.replace(incident);
  selectedId.value = null;
  map.value?.flyTo(incident.latitude, incident.longitude, 15);
}

let toastTimer: ReturnType<typeof setTimeout> | undefined;
function flash(message: string, ms = 3200) {
  toast.value = message;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { toast.value = null; }, ms);
}

async function onSubscribed() {
  await start();
}

async function logout() {
  await device.link(true);
  await auth.logout();
  await sync.clear();
  navigateTo('/app/start', { replace: true });
}

/** Alles wat na inloggen en met een actief abonnement moet draaien. */
async function start() {
  if (!auth.account?.subscription.active) return;
  await places.load();
  if (device.native) await device.createChannels();
  await configureRunner(places.places.value);
  await locate();
  if (!stopWatching && !locationDenied.value) {
    const stop = await watchCurrentPosition(onPosition).catch(() => undefined);
    if (disposed) stop?.();
    else stopWatching = stop;
  }
  if (device.native) {
    await device.registerPush().catch(() => undefined);
  }
  const fromLink = Number(route.query.incident);
  if (Number.isInteger(fromLink) && fromLink > 0) {
    openedFromAlert = true;
    select(fromLink);
  }
  await ads.countOpen();
  scheduleAd();
}

let observer: ResizeObserver | undefined;
function measurePanel() {
  const panel = panelWrap.value?.querySelector('.nearby') as HTMLElement | null;
  if (panel?.offsetHeight) panelInset.value = Math.round(window.innerHeight - panel.getBoundingClientRect().top);
}
let clock: ReturnType<typeof setInterval> | undefined;
let stopWatching: (() => void) | undefined;
let disposed = false;
let lastLocationCheck = 0;
let checkingLocation = false;
const listeners: Array<{ remove: () => Promise<void> }> = [];

async function onPosition(position: Position) {
  if (disposed || document.hidden || !auth.account?.subscription.active) return;
  const previous = me.value;
  me.value = position;
  // Keep the map moving; throttle sync/check work without depending on network access.
  if (checkingLocation || (previous && Date.now() - lastLocationCheck < 30_000 && distanceMeters(previous.latitude, previous.longitude, position.latitude, position.longitude) < 100)) return;
  lastLocationCheck = Date.now();
  checkingLocation = true;
  try { await sync.checkNearby(position); await sync.sync(position); await sync.checkNearby(position); }
  finally { checkingLocation = false; }
}

function onOffline() { sync.status.value = 'offline'; }
function onOnline() { if (auth.account?.subscription.active) void locate(false, true); }

onMounted(async () => {
  if (!auth.isLoggedIn) return navigateTo('/app/start', { replace: true });
  votes.value = await storage.get('vb_votes') ?? {};
  await sync.restore();

  // Hoeveel van het scherm het paneel (plus tabbalk) bedekt: daarboven komen de meldknop en het kaartmidden.
  observer = new ResizeObserver(measurePanel);
  const panel = panelWrap.value?.querySelector('.nearby') as HTMLElement | null;
  if (panel) observer.observe(panel);

  if (!auth.account) await auth.refreshAccount().catch(() => undefined);
  // Token ongeldig (account weg of uitgelogd op een ander apparaat): opnieuw inloggen.
  if (!auth.isLoggedIn) return navigateTo('/app/start', { replace: true });
  await start();
  if (disposed) return;
  window.addEventListener('offline', onOffline);
  window.addEventListener('online', onOnline);

  // Elke minuut: tijdslabels bijwerken en, alleen als de cache oud is, opnieuw synchroniseren.
  clock = setInterval(() => {
    tick.value++;
    sync.pruneExpired();
    if (me.value && auth.account?.subscription.active) void locate();
  }, 60_000);

  if (device.native) {
    listeners.push(await App.addListener('appStateChange', ({ isActive }) => {
      if (isActive && auth.account?.subscription.active) {
        auth.refreshAccount().catch(() => undefined);
        locate();
        void ads.countOpen().then(scheduleAd);
      } else if (!isActive) {
        clearTimeout(adTimer);
        void ads.hide();
      }
    }));
    listeners.push(await BackgroundRunner.addListener('backgroundRunnerNotificationReceived', ({ notificationId }) => {
      openedFromAlert = true;
      if (notificationId < 2_000_000_000) openFromNotification({ incident_id: notificationId });
    }));
  }
});

onBeforeUnmount(() => {
  disposed = true;
  stopWatching?.();
  window.removeEventListener('offline', onOffline);
  window.removeEventListener('online', onOnline);
  observer?.disconnect();
  clearInterval(clock);
  clearTimeout(adTimer);
  void ads.hide();
  ads.dispose();
  listeners.forEach((listener) => listener.remove());
});

// Zodra je iets gaat doen of er gevaar opduikt, verdwijnt de banner meteen.
watch([idle, dangerHere], ([isIdle, danger]) => { if (!isIdle || danger) void ads.hide(); });
// Het paneel schuift mee met de banner; de meldknop en het kaartmidden volgen.
watch(() => ads.height.value, () => setTimeout(measurePanel, 320));

watch(() => route.query.incident, (value) => {
  const id = Number(value);
  if (Number.isInteger(id) && id > 0) select(id);
});
watch(tab, () => { selectedId.value = null; });
watch(useLocale().locale, () => {
  const origin = me.value ?? mapCenter();
  if (origin) sync.sync(origin, { force: true });
});
</script>

<style scoped>
.topbar { position: absolute; z-index: 10; top: calc(var(--safe-top) + 10px); right: 12px; left: 12px; display: flex; align-items: center; justify-content: space-between; pointer-events: none; }
.topbar > * { pointer-events: auto; }
.topbar__state { width: 8px; height: 8px; margin: 0 -2px 0 -4px; border-radius: 50%; background: var(--vb-ok); box-shadow: 0 0 0 3px #e8f5ee; flex: none; }
.topbar__state.is-cached { background: var(--vb-subtle); box-shadow: 0 0 0 3px var(--vb-paper); }
.topbar__state.is-offline { background: var(--vb-warn); box-shadow: 0 0 0 3px #fff3e5; }
.topbar__brand { display: flex; align-items: center; gap: 10px; height: 44px; padding: 0 14px 0 9px; border-radius: 14px; background: #ffffffe6; box-shadow: var(--vb-shadow); color: var(--vb-muted); font-size: 13px; font-weight: 540; backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); }
.report-fab { position: absolute; z-index: 16; right: 14px; display: inline-flex; align-items: center; gap: 8px; height: 52px; padding: 0 20px 0 16px; border: 0; border-radius: 99px; color: #fff !important; background: var(--vb-ink); box-shadow: 0 8px 24px #0b0d1240; font-size: 16px; font-weight: 640; letter-spacing: -.01em; transition: transform .15s var(--vb-ease), bottom .3s var(--vb-ease); }
.report-fab:active { transform: scale(.96); }
.locate-cta { position: absolute; z-index: 16; left: 14px; min-height: 52px; border-radius: 99px; }
</style>
