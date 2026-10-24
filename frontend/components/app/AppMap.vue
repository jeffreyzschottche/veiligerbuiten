<template>
  <div ref="container" class="app-map" :class="{ 'is-ready': ready }" />
</template>

<script setup lang="ts">
import type { Map as MapLibreMap, GeoJSONSource } from 'maplibre-gl';
import type { Incident, Place } from '~/types/Incident';
import type { Position } from '~/composables/useDevice';

const props = defineProps<{
  incidents: Incident[];
  places: Place[];
  me: Position | null;
  selectedId: number | null;
  /** Ruimte onderin (px) die door het paneel wordt bedekt; de kaart centreert erboven. */
  bottomInset: number;
}>();
const emit = defineEmits<{ select: [id: number | null]; moved: [] }>();

// OpenFreeMap: open data (OpenStreetMap), geen sleutel, commercieel gebruik toegestaan.
const STYLE = 'https://tiles.openfreemap.org/styles/positron';
const LEVEL_COLOR = ['match', ['get', 'level'], 'danger', '#e5484d', 'warn', '#ff8a00', '#64708a'];
// Blauw is jouw eigen locatie; 'goed om te weten' is daarom neutraal leigrijs.
const AMSTERDAM: [number, number] = [4.8952, 52.3702];

const container = ref<HTMLDivElement | null>(null);
const ready = ref(false);
let map: MapLibreMap | null = null;
let centeredOnMe = false;

/** Een cirkel in meters als polygoon, zodat het bereik op elke zoom klopt. */
function circle(latitude: number, longitude: number, meters: number, steps = 64) {
  const coordinates: [number, number][] = [];
  const latRadius = meters / 111_320;
  const lngRadius = meters / (111_320 * Math.cos((latitude * Math.PI) / 180));
  for (let i = 0; i <= steps; i++) {
    const angle = (i / steps) * Math.PI * 2;
    coordinates.push([longitude + lngRadius * Math.cos(angle), latitude + latRadius * Math.sin(angle)]);
  }
  return { type: 'Feature' as const, properties: {}, geometry: { type: 'Polygon' as const, coordinates: [coordinates] } };
}

const incidentData = computed(() => ({
  type: 'FeatureCollection' as const,
  features: props.incidents.map((incident) => ({
    type: 'Feature' as const,
    id: incident.id,
    properties: { id: incident.id, level: incident.level, scale: incident.dotScale ?? 1, selected: incident.id === props.selectedId ? 1 : 0, rank: { danger: 3, warn: 2, info: 1 }[incident.level] },
    geometry: { type: 'Point' as const, coordinates: [incident.longitude, incident.latitude] },
  })),
}));

const reachData = computed(() => {
  const incident = props.incidents.find((item) => item.id === props.selectedId);
  const feature = incident ? circle(incident.latitude, incident.longitude, incident.alertRadius) : null;
  if (feature) Object.assign(feature.properties, { level: incident!.level });
  return { type: 'FeatureCollection' as const, features: feature ? [feature] : [] };
});

const placeData = computed(() => ({
  type: 'FeatureCollection' as const,
  features: props.places.flatMap((place) => [
    { ...circle(place.latitude, place.longitude, place.radius_m), properties: { kind: 'area', enabled: place.alerts_enabled ? 1 : 0 } },
    { type: 'Feature' as const, properties: { kind: 'label', name: place.name }, geometry: { type: 'Point' as const, coordinates: [place.longitude, place.latitude] } },
  ]),
}));

const meData = computed(() => ({
  type: 'FeatureCollection' as const,
  features: props.me ? [{ type: 'Feature' as const, properties: {}, geometry: { type: 'Point' as const, coordinates: [props.me.longitude, props.me.latitude] } }] : [],
}));

function source(id: string) {
  return map?.getSource(id) as GeoJSONSource | undefined;
}

onMounted(async () => {
  const maplibre = (await import('maplibre-gl')).default;
  map = new maplibre.Map({
    container: container.value!,
    style: STYLE,
    center: props.me ? [props.me.longitude, props.me.latitude] : AMSTERDAM,
    zoom: props.me ? 13.5 : 11.5,
    attributionControl: { compact: true },
    pitchWithRotate: false,
    dragRotate: false,
    maxBounds: [[2.0, 50.2], [8.6, 54.2]],
    minZoom: 6,
  });
  map.touchZoomRotate.disableRotation();

  map.on('load', () => {
    if (!map) return;
    map.addSource('places', { type: 'geojson', data: placeData.value });
    map.addSource('reach', { type: 'geojson', data: reachData.value });
    map.addSource('incidents', { type: 'geojson', data: incidentData.value });
    map.addSource('me', { type: 'geojson', data: meData.value });

    map.addLayer({ id: 'place-area', type: 'fill', source: 'places', filter: ['==', ['get', 'kind'], 'area'], paint: { 'fill-color': '#0b0d12', 'fill-opacity': ['case', ['==', ['get', 'enabled'], 1], 0.04, 0.015] } });
    map.addLayer({ id: 'place-line', type: 'line', source: 'places', filter: ['==', ['get', 'kind'], 'area'], paint: { 'line-color': '#0b0d12', 'line-opacity': 0.35, 'line-width': 1.2, 'line-dasharray': [2, 2] } });
    map.addLayer({ id: 'place-label', type: 'symbol', source: 'places', filter: ['==', ['get', 'kind'], 'label'], layout: { 'text-field': ['get', 'name'], 'text-font': ['Noto Sans Bold'], 'text-size': 12, 'text-letter-spacing': 0.02 }, paint: { 'text-color': '#0b0d12', 'text-halo-color': '#ffffff', 'text-halo-width': 1.6 } });

    map.addLayer({ id: 'reach-fill', type: 'fill', source: 'reach', paint: { 'fill-color': LEVEL_COLOR as any, 'fill-opacity': 0.08 } });
    map.addLayer({ id: 'reach-line', type: 'line', source: 'reach', paint: { 'line-color': LEVEL_COLOR as any, 'line-opacity': 0.6, 'line-width': 1.5, 'line-dasharray': [3, 2] } });

    // De stip groeit met bevestigingen (dotScale 1–2) en een beetje met de zoom.
    // MapLibre staat ['zoom'] alleen toe als invoer van een interpolate op het hoogste niveau.
    const size = (base: number, extra: unknown = 0) => ['interpolate', ['linear'], ['zoom'],
      8, ['+', ['*', ['get', 'scale'], base * 0.6], extra],
      14, ['+', ['*', ['get', 'scale'], base], extra]];
    map.addLayer({ id: 'incident-halo', type: 'circle', source: 'incidents', layout: { 'circle-sort-key': ['get', 'rank'] }, paint: { 'circle-radius': size(15, ['*', ['get', 'selected'], 6]) as any, 'circle-color': LEVEL_COLOR as any, 'circle-opacity': ['case', ['==', ['get', 'selected'], 1], 0.28, 0.16] } });
    map.addLayer({ id: 'incident-dot', type: 'circle', source: 'incidents', layout: { 'circle-sort-key': ['get', 'rank'] }, paint: { 'circle-radius': size(6.5) as any, 'circle-color': LEVEL_COLOR as any, 'circle-stroke-color': '#ffffff', 'circle-stroke-width': ['case', ['==', ['get', 'selected'], 1], 3, 2] } });

    map.addLayer({ id: 'me-halo', type: 'circle', source: 'me', paint: { 'circle-radius': 18, 'circle-color': '#2f6bff', 'circle-opacity': 0.14 } });
    map.addLayer({ id: 'me-dot', type: 'circle', source: 'me', paint: { 'circle-radius': 7, 'circle-color': '#2f6bff', 'circle-stroke-color': '#ffffff', 'circle-stroke-width': 3 } });

    for (const layer of ['incident-halo', 'incident-dot']) {
      map.on('click', layer, (event) => {
        const id = Number(event.features?.[0]?.properties?.id);
        if (Number.isFinite(id)) emit('select', id);
      });
      map.on('mouseenter', layer, () => { map!.getCanvas().style.cursor = 'pointer'; });
      map.on('mouseleave', layer, () => { map!.getCanvas().style.cursor = ''; });
    }
    map.on('click', (event) => {
      if (!map!.queryRenderedFeatures(event.point, { layers: ['incident-halo', 'incident-dot'] }).length) emit('select', null);
    });
    map.on('dragstart', () => emit('moved'));
    ready.value = true;
  });
});

onBeforeUnmount(() => {
  map?.remove();
  map = null;
});

watch(incidentData, (data) => source('incidents')?.setData(data));
watch(reachData, (data) => source('reach')?.setData(data));
watch(placeData, (data) => source('places')?.setData(data));
watch(meData, (data) => source('me')?.setData(data));
watch(() => props.me, (me) => {
  if (me && !centeredOnMe && map) {
    centeredOnMe = true;
    map.jumpTo({ center: [me.longitude, me.latitude], zoom: 13.5 });
  }
});

function padding() {
  return { top: 80, bottom: props.bottomInset + 24, left: 24, right: 24 };
}

defineExpose({
  flyTo(latitude: number, longitude: number, zoom?: number) {
    map?.flyTo({ center: [longitude, latitude], zoom: zoom ?? Math.max(map.getZoom(), 14), padding: padding(), duration: 600, essential: true });
  },
  /** Zoomt zo dat het hele bereik van een incident in beeld is. */
  showReach(incident: Incident) {
    if (!map) return;
    const lat = incident.alertRadius / 111_320;
    const lng = incident.alertRadius / (111_320 * Math.cos((incident.latitude * Math.PI) / 180));
    map.fitBounds([[incident.longitude - lng, incident.latitude - lat], [incident.longitude + lng, incident.latitude + lat]], { padding: padding(), maxZoom: 15.5, duration: 600 });
  },
  center(): Position | null {
    if (!map) return null;
    const { lat, lng } = map.getCenter();
    return { latitude: lat, longitude: lng, accuracy: 0 };
  },
});
</script>

<style scoped>
.app-map { position: absolute; inset: 0; background: #f2f3f5; opacity: 0; transition: opacity .4s; }
.app-map.is-ready { opacity: 1; }
.app-map :deep(.maplibregl-ctrl-bottom-right) { bottom: v-bind('`${props.bottomInset + 6}px`'); }
.app-map :deep(.maplibregl-ctrl-attrib) { font-size: 10px; }
.app-map :deep(.maplibregl-ctrl-attrib.maplibregl-compact) { background: #ffffffcc; }
</style>
