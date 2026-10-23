<template>
  <div class="veilig-buiten">
    <MapCanvas ref="mapCanvas" :incidents="visibleIncidents" :initial-focus="sharedLocation" @select="selectIncident" @location="updateLocation" />

    <header class="app-header">
      <NuxtLink to="/app" class="brand" aria-label="VeiligBuiten kaart"><span class="brand-mark"><Icon name="shield-alert" :size="18" /></span><span>Veilig<span>Buiten</span></span></NuxtLink>
      <nav class="desktop-nav" aria-label="Hoofdnavigatie"><button class="active"><Icon name="map" :size="17" />Kaart</button><button @click="panel = 'alerts'"><Icon name="bell" :size="17" />Alerts</button><button @click="panel = 'more'"><Icon name="settings" :size="17" />Instellingen</button></nav>
      <button class="header-action" @click="showInfo = !showInfo" aria-label="Informatie"><Icon name="info" :size="18" /></button>
    </header>

    <main class="map-ui">
      <aside class="desktop-sidebar" aria-label="Situaties in de omgeving">
        <div class="sidebar-heading"><p class="eyebrow">VEILIGBUITEN</p><h1>Wat speelt er in je omgeving?</h1><p>Actuele signalen binnen 25 kilometer van je locatie.</p></div>
        <div class="sidebar-actions"><button class="sidebar-locate" @click="locate"><Icon name="locate" :size="18" />Mijn locatie</button><button class="sidebar-refresh" :aria-label="loading ? 'Vernieuwen' : 'Kaart vernieuwen'" @click="refresh"><Icon name="refresh" :size="18" /></button></div>
        <section class="sidebar-filters"><div><b>Filter kaart</b><button @click="clearFilters">Wis filters</button></div><button v-for="filter in filters" :key="filter.value" :class="{ selected: selectedFilters.includes(filter.value) }" @click="toggleFilter(filter.value)"><Icon :name="filter.icon" :size="16" />{{ filter.label }}</button></section>
        <section class="incident-list"><div class="incident-list__heading"><b>Actuele situaties</b><span>{{ visibleIncidents.length }}</span></div><button v-for="incident in visibleIncidents" :key="incident.id" class="incident-list__item" @click="selectIncident(incident)"><span class="incident-list__icon" :class="`is-${incident.category}`"><Icon :name="categoryIcon(incident.category)" :size="17" /></span><span><b>{{ incident.title }}</b><small>{{ incident.distance }} · {{ incident.reportedAt }}<template v-if="incident.sourceName"> · {{ incident.sourceName }}</template></small></span><Icon name="chevron" :size="16" /></button><p v-if="!visibleIncidents.length" class="incident-list__empty">Geen actuele situaties voor deze filters.</p></section>
        <button class="desktop-report" @click="reportOpen = true"><Icon name="plus" :size="19" />Melding maken</button>
      </aside>
    </main>

    <section class="map-message" :class="{ 'map-message--error': loadError }"><span class="map-message__dot" /> {{ loadError ?? (loading ? 'Kaart vernieuwen…' : 'Actuele situaties in jouw omgeving') }}</section>

    <div class="map-tools">
      <button class="tool-button" aria-label="Centreer op jouw locatie" @click="locate"><Icon name="locate" :size="21" /></button>
      <button class="tool-button" :class="{ active: filtersOpen }" aria-label="Filters" @click="filtersOpen = !filtersOpen"><Icon name="filter" :size="21" /></button>
    </div>

    <Transition name="fade"><section v-if="filtersOpen" class="filters"><template v-if="!activeFilterCategory"><p>Toon op de kaart</p><button v-for="filter in filters" :key="filter.value" :class="{ selected: selectedFilters.includes(filter.value) }" @click="openSubtypeFilters(filter.value)"><Icon :name="filter.icon" :size="15" />{{ filter.label }}<Icon name="chevron" :size="14" /></button></template><template v-else><button class="filters-back" @click="activeFilterCategory = null">← Alle categorieën</button><p>{{ activeFilter.label }}</p><button class="filters-all" :class="{ selected: selectedFilters.includes(activeFilterCategory) && !hasSubtypeFilter(activeFilterCategory) }" @click="toggleFilter(activeFilterCategory)">Alles in {{ activeFilter.label }}</button><button v-for="subtype in activeSubtypes" :key="subtype" :class="{ selected: hasSubtypeFilter(activeFilterCategory, subtype) }" @click="toggleSubtypeFilter(activeFilterCategory, subtype)">{{ subtype }}</button></template></section></Transition>
    <Transition name="fade"><section v-if="showInfo" class="info-card"><button aria-label="Sluiten" @click="showInfo = false"><Icon name="close" :size="19" /></button><strong>VeiligBuiten is een omgevingsradar.</strong><p>Informatie is actueel, lokaal en geen garantie dat er niets gebeurt. Bel 112 bij direct gevaar.</p></section></Transition>

    <section class="nearby-strip"><span>IN JOUW OMGEVING</span><button @click="refresh"><Icon name="refresh" :size="14" />Vernieuwen</button><div><strong>{{ visibleIncidents.length }}</strong> actuele situaties binnen 25 km</div></section>
    <button class="report-button" @click="reportOpen = true"><span><Icon name="plus" :size="18" /></span> Melding maken</button>

    <nav class="bottom-nav" aria-label="Hoofdnavigatie"><button class="active"><Icon name="map" :size="20" /><span>Kaart</span></button><button @click="panel = 'alerts'"><Icon name="bell" :size="20" /><span>Alerts</span></button><button @click="panel = 'more'"><Icon name="settings" :size="20" /><span>Meer</span></button></nav>
    <IncidentSheet :incident="selectedIncident" :vote="selectedIncident ? incidentVotes[selectedIncident.id] : null" @close="selectedIncident = null" @confirm="confirm" @resolved="resolve" @open-maps="openMaps" @share="shareIncident" />
    <ReportFlow :open="reportOpen" :success="reportSuccess" :submitting="reportSubmitting" :error="reportError" @close="closeReport" @submit="createReport" />
    <Transition name="sheet"><aside v-if="panel" class="product-panel"><button class="sheet-close" aria-label="Sluiten" @click="panel = null"><Icon name="close" :size="18" /></button><template v-if="panel === 'alerts'"><p class="eyebrow">ALERTVOORKEUREN</p><h2>Waarschuwingen dichtbij</h2><p>Ontvang alleen een melding wanneer een situatie in jouw omgeving relevant is.</p><label v-for="preference in preferences" :key="preference.key" class="preference"><span><b>{{ preference.label }}</b><small>{{ preference.description }}</small></span><input v-model="preference.enabled" type="checkbox"></label><button class="save-preferences" @click="savePreferences">Voorkeuren opslaan</button></template><template v-else><p class="eyebrow">VEILIGBUITEN</p><h2>Rustig en lokaal</h2><p>VeiligBuiten toont actuele signalen in jouw omgeving. Informatie is geen garantie dat er niets gebeurt.</p><button class="settings-row" @click="locate"><Icon name="locate" :size="18" />Locatie opnieuw ophalen <Icon name="chevron" :size="16" /></button><button class="settings-row" @click="showInfo = true"><Icon name="info" :size="18" />Privacy en veiligheid <Icon name="chevron" :size="16" /></button><p class="panel-version">Versie 0.1 · Nederlandse MVP</p></template></aside></Transition>
  </div>
</template>

<script setup lang="ts">
import type { Incident, IncidentCategory } from '~/types/Incident';
import { apiFetch } from '~/services/apiFetch';
import { reportCategories, reportSubtypes } from '~/utils/reportTaxonomy';
useHead({ title: 'VeiligBuiten — actuele situaties in je omgeving', meta: [{ name: 'theme-color', content: '#10211e' }] });
const { incidents, loading, loadError, refresh, byCategories } = useIncidents();
const route = useRoute();
const coordinate = (value: unknown) => { const number = Number(Array.isArray(value) ? value[0] : value); return Number.isFinite(number) ? number : null; };
const sharedLocation = computed(() => { const latitude = coordinate(route.query.lat); const longitude = coordinate(route.query.lng); return latitude !== null && longitude !== null && latitude >= 50.5 && latitude <= 53.7 && longitude >= 3.1 && longitude <= 7.3 ? { latitude, longitude } : null; });
const sharedIncidentId = computed(() => Number(Array.isArray(route.query.incident) ? route.query.incident[0] : route.query.incident));
const selectedIncident = ref<Incident | null>(null); const reportOpen = ref(false); const reportSuccess = ref(false); const reportSubmitting = ref(false); const reportError = ref<string | null>(null); const filtersOpen = ref(false); const showInfo = ref(false); const panel = ref<'alerts' | 'more' | null>(null); const mapCanvas = ref<{ requestLocation: () => void; focusOn: (latitude: number, longitude: number) => void } | null>(null); const lastPosition = ref<GeolocationPosition | null>(null);
const preferences = ref([{ key: 'direct', label: 'Direct gevaar', description: 'Ernstige situaties dichtbij', enabled: true }, { key: 'warning', label: 'Waarschuwingen', description: 'Relevante verstoringen in de buurt', enabled: true }, { key: 'info', label: 'Informatie', description: 'Bijvoorbeeld wegafsluitingen', enabled: false }]);
const selectedFilters = ref<IncidentCategory[]>([]);
const selectedSubtypeFilters = ref<Array<{ category: IncidentCategory; subtype: string }>>([]);
const activeFilterCategory = ref<IncidentCategory | null>(null);
const incidentVotes = ref<Record<number, 'present' | 'resolved'>>({});
const votingIncidentIds = ref<number[]>([]);
const filters = reportCategories;
const activeFilter = computed(() => filters.find((filter) => filter.value === activeFilterCategory.value) ?? filters[0]);
const activeSubtypes = computed(() => activeFilterCategory.value ? reportSubtypes[activeFilterCategory.value] : []);
const visibleIncidents = computed(() => byCategories(selectedFilters.value).filter((incident) => {
  const subtypeFilters = selectedSubtypeFilters.value.filter((filter) => filter.category === incident.category);
  return !subtypeFilters.length || subtypeFilters.some((filter) => filter.subtype === (incident.subtype ?? incident.title));
}));
function hasSubtypeFilter(category: IncidentCategory, subtype?: string) { return selectedSubtypeFilters.value.some((filter) => filter.category === category && (subtype === undefined || filter.subtype === subtype)); }
function clearFilters() { selectedFilters.value = []; selectedSubtypeFilters.value = []; }
function toggleFilter(category: IncidentCategory) {
  if (selectedFilters.value.includes(category)) { selectedFilters.value = selectedFilters.value.filter((value) => value !== category); selectedSubtypeFilters.value = selectedSubtypeFilters.value.filter((filter) => filter.category !== category); return; }
  selectedFilters.value = [...selectedFilters.value, category];
}
function openSubtypeFilters(category: IncidentCategory) { activeFilterCategory.value = category; }
function toggleSubtypeFilter(category: IncidentCategory, subtype: string) {
  if (!selectedFilters.value.includes(category)) selectedFilters.value = [...selectedFilters.value, category];
  selectedSubtypeFilters.value = hasSubtypeFilter(category, subtype) ? selectedSubtypeFilters.value.filter((filter) => !(filter.category === category && filter.subtype === subtype)) : [...selectedSubtypeFilters.value, { category, subtype }];
}
function categoryIcon(category: IncidentCategory) { return ({ brand: 'fire', geweld: 'shield-alert', onveilig: 'alert-triangle', verkeer: 'car', blokkade: 'construction', weer: 'cloud-rain', stoffen: 'flask', drukte: 'users', dier: 'paw', anders: 'circle-help' } as const)[category]; }
function locate() { mapCanvas.value?.requestLocation(); }
function selectIncident(incident: Incident) {
  selectedIncident.value = incident;
  if (incident.latitude !== undefined && incident.longitude !== undefined) mapCanvas.value?.focusOn(incident.latitude, incident.longitude);
}
function distanceInMetres(latitudeA: number, longitudeA: number, latitudeB: number, longitudeB: number) {
  const toRadians = (value: number) => value * Math.PI / 180;
  const latitudeDistance = toRadians(latitudeB - latitudeA);
  const longitudeDistance = toRadians(longitudeB - longitudeA);
  const a = Math.sin(latitudeDistance / 2) ** 2 + Math.cos(toRadians(latitudeA)) * Math.cos(toRadians(latitudeB)) * Math.sin(longitudeDistance / 2) ** 2;
  return 6_371_000 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}
function formatDistance(distance: number) { return distance < 1_000 ? `${Math.round(distance / 10) * 10} m` : `${(distance / 1_000).toFixed(1).replace('.', ',')} km`; }
async function updateLocation(position: GeolocationPosition) {
  lastPosition.value = position;
  try {
    const token = await useInstallation().ensure();
    await apiFetch('/installations/location', { method: 'PUT', headers: { 'X-Installation-Token': token }, body: JSON.stringify({ latitude: position.coords.latitude, longitude: position.coords.longitude, accuracy_m: position.coords.accuracy }) });
  } catch { /* A kaartbezoek blijft werken als de locatie-sync tijdelijk niet lukt. */ }
  await refresh(sharedLocation.value?.latitude ?? position.coords.latitude, sharedLocation.value?.longitude ?? position.coords.longitude);
  incidents.value.forEach((incident) => {
    if (incident.latitude === undefined || incident.longitude === undefined) return;
    incident.distance = formatDistance(distanceInMetres(position.coords.latitude, position.coords.longitude, incident.latitude, incident.longitude));
  });
  if (selectedIncident.value?.latitude !== undefined && selectedIncident.value.longitude !== undefined) {
    selectedIncident.value = {
      ...selectedIncident.value,
      distance: formatDistance(distanceInMetres(position.coords.latitude, position.coords.longitude, selectedIncident.value.latitude, selectedIncident.value.longitude)),
    };
  }
}
function saveIncidentVotes() { localStorage.setItem('veiligbuiten-incident-votes', JSON.stringify(incidentVotes.value)); }
async function voteIncident(vote: 'present' | 'resolved') {
  const incident = selectedIncident.value;
  if (!incident || incidentVotes.value[incident.id] || votingIncidentIds.value.includes(incident.id)) return;
  votingIncidentIds.value = [...votingIncidentIds.value, incident.id];
  try {
    const token = await useInstallation().ensure();
    const latitude = lastPosition.value?.coords.latitude;
    const longitude = lastPosition.value?.coords.longitude;
    const { data } = await apiFetch<{ data: Incident }>(`/incidents/${incident.id}/${vote === 'present' ? 'confirm' : 'resolve'}`, {
      method: 'POST',
      headers: { 'X-Installation-Token': token },
      body: JSON.stringify({ latitude, longitude }),
    });
    incidents.value = incidents.value.map((candidate) => candidate.id === data.id ? data : candidate);
    selectedIncident.value = data;
    incidentVotes.value = { ...incidentVotes.value, [incident.id]: vote };
    saveIncidentVotes();
  } catch (error) {
    const detail = error instanceof Error ? error.message : 'Je beoordeling kon niet worden opgeslagen.';
    loadError.value = detail;
  } finally {
    votingIncidentIds.value = votingIncidentIds.value.filter((id) => id !== incident.id);
  }
}
function confirm() { return voteIncident('present'); }
function resolve() { return voteIncident('resolved'); }
function openMaps() {
  const incident = selectedIncident.value;
  if (!incident) return;

  // Google Maps vindt automatisch het dichtstbijzijnde beschikbare Street
  // View-punt. Hiervoor is geen Google Maps API-key nodig.
  const latitude = incident.latitude ?? lastPosition.value?.coords.latitude ?? 52.3702;
  const longitude = incident.longitude ?? lastPosition.value?.coords.longitude ?? 4.8952;
  const url = new URL('https://www.google.com/maps/@');
  url.searchParams.set('api', '1');
  url.searchParams.set('map_action', 'pano');
  url.searchParams.set('viewpoint', `${latitude},${longitude}`);
  url.searchParams.set('heading', '0');
  url.searchParams.set('pitch', '0');
  url.searchParams.set('fov', '80');
  window.open(url.toString(), '_blank', 'noopener,noreferrer');
}
async function shareIncident() {
  const incident = selectedIncident.value;
  if (!incident) return;
  const latitude = incident.latitude ?? lastPosition.value?.coords.latitude;
  const longitude = incident.longitude ?? lastPosition.value?.coords.longitude;
  if (latitude === undefined || longitude === undefined) return;
  const url = new URL(window.location.origin + route.path);
  url.searchParams.set('lat', String(latitude)); url.searchParams.set('lng', String(longitude)); url.searchParams.set('incident', String(incident.id));
  const data = { title: `VeiligBuiten: ${incident.title}`, text: `Bekijk deze situatie in VeiligBuiten: ${incident.title}.`, url: url.toString() };
  if (navigator.share) await navigator.share(data); else await navigator.clipboard.writeText(data.url);
}
async function openSharedIncident() {
  if (!Number.isInteger(sharedIncidentId.value) || !sharedLocation.value) return;
  try {
    const viewerLatitude = lastPosition.value?.coords.latitude ?? sharedLocation.value.latitude;
    const viewerLongitude = lastPosition.value?.coords.longitude ?? sharedLocation.value.longitude;
    const { data } = await apiFetch<{ data: Incident }>(`/incidents/${sharedIncidentId.value}?latitude=${viewerLatitude}&longitude=${viewerLongitude}`);
    // The shared coordinates are the incident location, never the viewer's location.
    // Replace the temporary API distance as soon as geolocation is available.
    if (!lastPosition.value) data.distance = 'Locatie van melding';
    selectedIncident.value = data;
    if (!incidents.value.some((incident) => incident.id === data.id)) incidents.value = [data, ...incidents.value];
  } catch { /* A verlopen of onbekende gedeelde melding opent niet. */ }
}
function closeReport() { reportOpen.value = false; reportSuccess.value = false; reportSubmitting.value = false; reportError.value = null; }
async function createReport(payload: { category: IncidentCategory; subtype: string; description: string; captchaToken: string }) {
  if (reportSubmitting.value) return;
  reportSubmitting.value = true; reportError.value = null;
  const position = await new Promise<GeolocationPosition | null>((resolve) => navigator.geolocation?.getCurrentPosition(resolve, () => resolve(null), { enableHighAccuracy: true, timeout: 8000, maximumAge: 0 }) ?? resolve(null));
  try {
    if (!position) throw new Error('We hebben je actuele locatie nodig om een melding te plaatsen.');
    const token = await useInstallation().ensure();
    await apiFetch('/reports', { method: 'POST', headers: { 'X-Installation-Token': token }, body: JSON.stringify({ category: payload.category, subtype: payload.subtype, description: payload.description, captcha_token: payload.captchaToken, latitude: position.coords.latitude, longitude: position.coords.longitude, user_latitude: position.coords.latitude, user_longitude: position.coords.longitude, location_accuracy_m: position.coords.accuracy }) });
    await refresh(position.coords.latitude, position.coords.longitude);
    reportSuccess.value = true;
  } catch (error) {
    reportError.value = error instanceof Error ? error.message : 'Melding kon niet worden geplaatst. Probeer opnieuw.';
  } finally {
    reportSubmitting.value = false;
  }
}
async function savePreferences() {
  localStorage.setItem('veiligbuiten-alerts', JSON.stringify(preferences.value));
  try {
    const token = await useInstallation().ensure();
    await apiFetch('/installations/preferences', {
      method: 'PUT',
      headers: { 'X-Installation-Token': token },
      body: JSON.stringify({ preferences: Object.fromEntries(preferences.value.map(({ key, enabled }) => [key, enabled])) }),
    });
    await usePushNotifications().enable();
  } catch {
    // Preferences remain local when the API or native permission flow is unavailable.
  }
  panel.value = null;
}
onMounted(async () => {
  try { incidentVotes.value = JSON.parse(localStorage.getItem('veiligbuiten-incident-votes') ?? '{}'); } catch { incidentVotes.value = {}; }
  refresh(sharedLocation.value?.latitude, sharedLocation.value?.longitude);
  await openSharedIncident();
});
watch(incidents, () => { if (Number.isInteger(sharedIncidentId.value)) selectedIncident.value = incidents.value.find((incident) => incident.id === sharedIncidentId.value) ?? null; }, { immediate: true });
</script>
