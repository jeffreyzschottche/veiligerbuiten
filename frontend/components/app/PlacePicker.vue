<template>
  <div class="picker">
    <div ref="container" class="picker__map" :class="{ 'is-ready': ready }" />
    <!-- De speld staat vast in het midden; je schuift de kaart eronder. -->
    <span class="picker__pin" :style="{ color: hex }" aria-hidden="true"><Icon name="pin" :size="30" :stroke="2.2" /></span>
    <button type="button" class="picker__locate" :aria-label="t.places.myLocation" @click="emit('locate')"><Icon name="locate" :size="18" /></button>
  </div>
</template>

<script setup lang="ts">
import type { Map as MapLibreMap, GeoJSONSource } from 'maplibre-gl';
import { circleBounds, circleFeature } from '~/utils/geoCircle';
import { placeColor, type PlaceColor } from '~/utils/placeColors';

type Point = { latitude: number; longitude: number };

const props = defineProps<{ point: Point | null; radius: number; color: PlaceColor }>();
const emit = defineEmits<{ 'update:point': [point: Point]; locate: [] }>();
const { t } = useAppCopy();

const STYLE = 'https://tiles.openfreemap.org/styles/positron';
const AMSTERDAM: Point = { latitude: 52.3702, longitude: 4.8952 };

const container = ref<HTMLDivElement | null>(null);
const ready = ref(false);
const hex = computed(() => placeColor(props.color));
let map: MapLibreMap | null = null;

function areaData(center: Point) {
  return { type: 'FeatureCollection' as const, features: [circleFeature(center.latitude, center.longitude, props.radius)] };
}

function center(): Point {
  const c = map!.getCenter();
  return { latitude: c.lat, longitude: c.lng };
}

/** Zoom zo dat de hele straal in beeld is, met wat ruimte eromheen. */
function frame(point: Point, animate: boolean) {
  map?.fitBounds(circleBounds(point.latitude, point.longitude, props.radius), { padding: 28, animate, maxZoom: 17 });
}

onMounted(async () => {
  const maplibre = (await import('maplibre-gl')).default;
  const start = props.point ?? AMSTERDAM;
  map = new maplibre.Map({
    container: container.value!,
    style: STYLE,
    center: [start.longitude, start.latitude],
    zoom: 14,
    attributionControl: { compact: true },
    pitchWithRotate: false,
    dragRotate: false,
    maxBounds: [[2.0, 50.2], [8.6, 54.2]],
    minZoom: 6,
  });
  map.touchZoomRotate.disableRotation();
  frame(start, false);

  map.on('load', () => {
    if (!map) return;
    map.addSource('area', { type: 'geojson', data: areaData(center()) });
    map.addLayer({ id: 'area-fill', type: 'fill', source: 'area', paint: { 'fill-color': hex.value, 'fill-opacity': 0.1 } });
    map.addLayer({ id: 'area-line', type: 'line', source: 'area', paint: { 'line-color': hex.value, 'line-opacity': 0.8, 'line-width': 1.5, 'line-dasharray': [2, 2] } });
    ready.value = true;
  });
  // De cirkel beweegt mee terwijl je sleept; de plek zelf geven we pas door als de kaart stilstaat.
  map.on('move', () => (map?.getSource('area') as GeoJSONSource | undefined)?.setData(areaData(center())));
  map.on('moveend', () => emit('update:point', center()));
});

onBeforeUnmount(() => {
  map?.remove();
  map = null;
});

// Van buitenaf verplaatst (bijv. "Mijn locatie"): spring erheen, maar niet terug na ons eigen slepen.
watch(() => props.point, (point) => {
  if (!map || !point) return;
  const now = center();
  if (Math.abs(now.latitude - point.latitude) > 1e-6 || Math.abs(now.longitude - point.longitude) > 1e-6) frame(point, true);
});
watch(() => props.radius, () => {
  if (!map) return;
  (map.getSource('area') as GeoJSONSource | undefined)?.setData(areaData(center()));
  frame(center(), true);
});
watch(hex, (color) => {
  if (!map?.getLayer('area-fill')) return;
  map.setPaintProperty('area-fill', 'fill-color', color);
  map.setPaintProperty('area-line', 'line-color', color);
});
</script>

<style scoped>
.picker { position: relative; height: 220px; overflow: hidden; border-radius: var(--vb-radius); background: #f2f3f5; box-shadow: inset 0 0 0 1px var(--vb-line); }
.picker__map { position: absolute; inset: 0; opacity: 0; transition: opacity .3s; }
.picker__map.is-ready { opacity: 1; }
.picker__map :deep(.maplibregl-ctrl-attrib) { font-size: 10px; }
.picker__map :deep(.maplibregl-ctrl-attrib.maplibregl-compact) { background: #ffffffcc; }
/* De punt van de speld wijst naar het midden van de kaart. */
.picker__pin { position: absolute; left: 50%; top: 50%; transform: translate(-50%, -87.5%); pointer-events: none; filter: drop-shadow(0 2px 2px #0b0d1240); }
.picker__pin :deep(svg) { fill: #fff; }
.picker__locate { position: absolute; top: 10px; right: 10px; display: grid; place-items: center; width: 38px; height: 38px; border: 0; border-radius: 12px; background: #fff; color: var(--vb-ink); box-shadow: var(--vb-shadow); }
</style>
