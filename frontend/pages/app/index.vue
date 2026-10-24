<template>
  <div class="vb-app">
    <AppMap
      ref="map"
      :incidents="sync.incidents.value"
      :places="places.places.value"
      :me="me"
      :selected-id="selectedId"
      :bottom-inset="panelInset"
      @select="select"
    />

    <header class="topbar">
      <div class="topbar__brand">
        <LogoMark :size="26" />
        <span>{{ statusText }}</span>
      </div>
      <button class="icon-btn" :aria-label="t.map.locate" @click="locate(true)"><Icon name="locate" :size="21" /></button>
    </header>

    <div v-show="tab === 'map' && !selected" ref="panelWrap">
      <NearbyPanel v-model:expanded="panelOpen" :items="nearby" :has-location="!!me" :status="statusText" @select="select" />
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
import type { Incident } from '~/types/Incident';
import type { Tab } from '~/types/App';
import { distanceMeters } from '~/composables/useGeo';
import { currentPosition, openFromNotification, type Position } from '~/composables/useDevice';
import { storage } from '~/utils/storage';

definePageMeta({ layout: false });
useHead({ title: 'VeiligBuiten', meta: [{ name: 'theme-color', content: '#f4f5f7' }] });

/** "In je buurt" in de lijst: binnen 5 km, of binnen het meldbereik van iets groots. */
const NEARBY_M = 5000;

const route = useRoute();
const { t, fill, ago } = useAppCopy();
const auth = useAuthStore();
const device = useDevice();
const sync = useSync();
const places = usePlaces();

const map = ref<{ flyTo: (lat: number, lng: number, zoom?: number) => void; showReach: (incident: Incident) => void; center: () => Position | null } | null>(null);
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
const selected = computed(() => sync.incidents.value.find((incident) => incident.id === selectedId.value) ?? null);
const selectedMeters = computed(() => selected.value && me.value ? distanceMeters(me.value.latitude, me.value.longitude, selected.value.latitude, selected.value.longitude) : null);

const nearby = computed(() => {
  const origin = me.value;
  if (!origin) return [];
  const rank = { danger: 3, warn: 2, info: 1 };
  return sync.incidents.value
    .map((incident) => ({ incident, distance: distanceMeters(origin.latitude, origin.longitude, incident.latitude, incident.longitude) }))
    .filter(({ incident, distance }) => distance <= Math.max(NEARBY_M, incident.alertRadius))
    .sort((a, b) => rank[b.incident.level] - rank[a.incident.level] || a.distance - b.distance);
});

const statusText = computed(() => {
  tick.value;
  if (sync.status.value === 'loading' && !sync.syncedAt.value) return '…';
  const when = sync.syncedAt.value ? ago(new Date(sync.syncedAt.value).toISOString()) : '';
  return sync.status.value === 'offline' ? fill(t.value.map.offline, { time: when }) : fill(t.value.map.updated, { time: when });
});

function mapCenter() {
  return map.value?.center() ?? null;
}

async function locate(fly = false) {
  try {
    const position = await currentPosition();
    me.value = position;
    locationDenied.value = false;
    if (fly) map.value?.flyTo(position.latitude, position.longitude, 14);
    await sync.sync(position);
  } catch {
    locationDenied.value = true;
    if (fly) flash(t.value.errors.location);
    // Zonder locatie synchroniseren we rond het midden van de kaart.
    const center = mapCenter();
    if (center) await sync.sync(center);
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

async function vote(kind: 'present' | 'resolved') {
  const incident = selected.value;
  if (!incident || votes.value[incident.id]) return;
  voting.value = true;
  try {
    await device.ensureInstallation();
    const { data } = await useApi().post<{ data: Incident }>(`/incidents/${incident.id}/${kind === 'present' ? 'confirm' : 'resolve'}`, {
      latitude: me.value?.latitude ?? null,
      longitude: me.value?.longitude ?? null,
    });
    sync.replace(data);
    votes.value = { ...votes.value, [incident.id]: kind };
    await storage.set('vb_votes', votes.value);
  } catch (caught: any) {
    if (caught?.status === 409) votes.value = { ...votes.value, [incident.id]: kind };
    else flash(caught?.data?.message ?? t.value.errors.generic);
  } finally {
    voting.value = false;
  }
}

function placed(incident: Incident) {
  sync.replace(incident);
  selectedId.value = null;
  map.value?.flyTo(incident.latitude, incident.longitude, 15);
}

let toastTimer: ReturnType<typeof setTimeout> | undefined;
function flash(message: string) {
  toast.value = message;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { toast.value = null; }, 3200);
}

async function onSubscribed() {
  await start();
}

async function logout() {
  await device.link(true);
  await auth.logout();
  useBilling().reset();
  await sync.clear();
  navigateTo('/app/start', { replace: true });
}

/** Alles wat na inloggen en met een actief abonnement moet draaien. */
async function start() {
  if (!auth.account?.subscription.active) return;
  await places.load();
  await configureRunner(places.places.value);
  await locate();
  if (device.native) {
    await device.createChannels();
    await device.registerPush();
  }
  const fromLink = Number(route.query.incident);
  if (Number.isInteger(fromLink) && fromLink > 0) select(fromLink);
}

let observer: ResizeObserver | undefined;
let clock: ReturnType<typeof setInterval> | undefined;
const listeners: Array<{ remove: () => Promise<void> }> = [];

onMounted(async () => {
  if (!auth.isLoggedIn) return navigateTo('/app/start', { replace: true });
  votes.value = await storage.get('vb_votes') ?? {};
  await sync.restore();

  // Hoeveel van het scherm het paneel (plus tabbalk) bedekt: daarboven komen de meldknop en het kaartmidden.
  const panel = panelWrap.value?.querySelector('.nearby') as HTMLElement | null;
  observer = new ResizeObserver(() => {
    if (panel?.offsetHeight) panelInset.value = Math.round(window.innerHeight - panel.getBoundingClientRect().top);
  });
  if (panel) observer.observe(panel);

  if (!auth.account) await auth.refreshAccount().catch(() => undefined);
  await start();

  // Elke minuut: tijdslabels bijwerken en, alleen als de cache oud is, opnieuw synchroniseren.
  clock = setInterval(() => {
    tick.value++;
    if (me.value && sync.isStale(me.value)) sync.sync(me.value);
  }, 60_000);

  if (device.native) {
    listeners.push(await App.addListener('appStateChange', ({ isActive }) => {
      if (isActive && auth.account?.subscription.active) {
        auth.refreshAccount().catch(() => undefined);
        locate();
      }
    }));
    listeners.push(await BackgroundRunner.addListener('backgroundRunnerNotificationReceived', ({ notificationId }) => {
      if (notificationId < 2_000_000_000) openFromNotification({ incident_id: notificationId });
    }));
  }
});

onBeforeUnmount(() => {
  observer?.disconnect();
  clearInterval(clock);
  listeners.forEach((listener) => listener.remove());
});

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
.topbar__brand { display: flex; align-items: center; gap: 10px; height: 44px; padding: 0 14px 0 9px; border-radius: 14px; background: #ffffffe6; box-shadow: var(--vb-shadow); color: var(--vb-muted); font-size: 13px; font-weight: 540; backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); }
.report-fab { position: absolute; z-index: 16; right: 14px; display: inline-flex; align-items: center; gap: 8px; height: 52px; padding: 0 20px 0 16px; border: 0; border-radius: 99px; color: #fff !important; background: var(--vb-ink); box-shadow: 0 8px 24px #0b0d1240; font-size: 16px; font-weight: 640; letter-spacing: -.01em; transition: transform .15s var(--vb-ease), bottom .3s var(--vb-ease); }
.report-fab:active { transform: scale(.96); }
.locate-cta { position: absolute; z-index: 16; left: 14px; min-height: 52px; border-radius: 99px; }
</style>
