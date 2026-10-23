<template>
  <section ref="mapElement" class="vb-map" :class="{ 'is-panning': panState }" aria-label="Kaart van je omgeving" @pointerdown="startPan" @pointermove="pan" @pointerup="endPan" @pointercancel="endPan">
    <div class="map-tile-grid" aria-hidden="true">
      <img v-for="tile in visibleTiles" :key="tile.key" :src="tile.url" :style="{ left: `${tile.left}px`, top: `${tile.top}px` }" alt="" />
    </div>
    <svg v-if="geometryPaths.length" class="map-geometries" :viewBox="`0 0 ${mapSize.width} ${mapSize.height}`" aria-hidden="true">
      <path v-for="feature in geometryPaths" :key="feature.id" :d="feature.path" :class="`map-geometries__${feature.kind}`" />
    </svg>
    <button v-for="incident in incidents" :key="`overlay-${incident.id}`" class="map-dom-marker" :style="markerStyle(incident)" :aria-label="incident.title" @click="emit('select', incident)" v-html="markerIcon(incident.category)" />
    <span v-if="userPosition" class="map-dom-user-location" :style="userMarkerStyle" aria-label="Jouw locatie" />
    <div class="map-zoom-controls" aria-label="Kaartzoom"><button aria-label="Inzoomen" :disabled="zoom >= 20" @click.stop="changeZoom(1)">+</button><button aria-label="Uitzoomen" :disabled="zoom <= 14" @click.stop="changeZoom(-1)">−</button></div>
    <div class="map-attribution">© OpenStreetMap · © CARTO</div>
  </section>
</template>

<script setup lang="ts">
import type { Incident } from '~/types/Incident';

const props = defineProps<{ incidents: Incident[]; initialFocus?: { latitude: number; longitude: number } | null }>();
const emit = defineEmits<{ select: [incident: Incident]; location: [position: GeolocationPosition] }>();
const defaultFocus: [number, number] = [52.3702, 4.8952];
// Start ruim genoeg om de directe omgeving te begrijpen, maar houd de kaart lokaal.
const zoom = ref(18);
const focus = ref(defaultFocus);
const userPosition = ref<GeolocationPosition | null>(null);
const mapElement = ref<HTMLElement | null>(null);
const mapSize = ref({ width: 1440, height: 900 });
const visibleTiles = computed(() => createTiles(focus.value[0], focus.value[1], zoom.value, mapSize.value));
const geometryPaths = computed(() => props.incidents.flatMap((incident) => geometryPath(incident)));
let resizeObserver: ResizeObserver | undefined;
const panState = ref<{ pointerId: number; startX: number; startY: number; startFocus: [number, number] } | null>(null);

function worldPoint(latitude: number, longitude: number, zoomLevel: number) {
  const z = Math.min(20, Math.max(14, Math.round(zoomLevel)));
  const worldSize = 256 * 2 ** z;
  const latRad = latitude * Math.PI / 180;
  return { x: ((longitude + 180) / 360) * worldSize, y: (1 - Math.asinh(Math.tan(latRad)) / Math.PI) / 2 * worldSize, worldSize };
}

function createTiles(latitude: number, longitude: number, zoomLevel: number, size: { width: number; height: number }) {
  const z = Math.min(20, Math.max(14, Math.round(zoomLevel)));
  const center = worldPoint(latitude, longitude, z);
  const centerTileX = Math.floor(center.x / 256);
  const centerTileY = Math.floor(center.y / 256);
  const horizontal = Math.ceil(size.width / 512) + 2;
  const vertical = Math.ceil(size.height / 512) + 2;
  const tileCount = 2 ** z;
  const tiles: { key: string; url: string; left: number; top: number }[] = [];
  for (let row = -vertical; row <= vertical; row += 1) for (let column = -horizontal; column <= horizontal; column += 1) {
    const rawX = centerTileX + column;
    const rawY = centerTileY + row;
    if (rawY < 0 || rawY >= tileCount) continue;
    const tileX = ((rawX % tileCount) + tileCount) % tileCount;
    tiles.push({ key: `${z}-${tileX}-${rawY}`, url: `https://a.basemaps.cartocdn.com/rastertiles/voyager/${z}/${tileX}/${rawY}@2x.png`, left: rawX * 256 - center.x + size.width / 2, top: rawY * 256 - center.y + size.height / 2 });
  }
  return tiles;
}

function changeZoom(delta: number) { zoom.value = Math.min(20, Math.max(14, zoom.value + delta)); }
function latitudeLongitudeAt(point: { x: number; y: number; worldSize: number }): [number, number] {
  const longitude = point.x / point.worldSize * 360 - 180;
  const latitude = Math.atan(Math.sinh(Math.PI * (1 - 2 * point.y / point.worldSize))) * 180 / Math.PI;
  return [Math.max(-85.0511, Math.min(85.0511, latitude)), longitude];
}
function startPan(event: PointerEvent) {
  if ((event.target as HTMLElement).closest('button')) return;
  panState.value = { pointerId: event.pointerId, startX: event.clientX, startY: event.clientY, startFocus: [...focus.value] };
  mapElement.value?.setPointerCapture(event.pointerId);
}
function pan(event: PointerEvent) {
  const state = panState.value;
  if (!state || state.pointerId !== event.pointerId) return;
  const start = worldPoint(state.startFocus[0], state.startFocus[1], zoom.value);
  focus.value = latitudeLongitudeAt({ x: start.x - (event.clientX - state.startX), y: start.y - (event.clientY - state.startY), worldSize: start.worldSize });
}
function endPan(event: PointerEvent) {
  if (panState.value?.pointerId !== event.pointerId) return;
  if (mapElement.value?.hasPointerCapture(event.pointerId)) mapElement.value.releasePointerCapture(event.pointerId);
  panState.value = null;
}
function markerColor(category: Incident['category']) { return ({ brand: '#d84935', geweld: '#d55a35', onveilig: '#d57b2d', verkeer: '#d79728', blokkade: '#bd7e1d', weer: '#377db4', stoffen: '#8f4a9e', drukte: '#7255a6', dier: '#657b3e', anders: '#65736d' } as Record<Incident['category'], string>)[category]; }
function markerIcon(category: Incident['category']) {
  const path = ({ brand: 'M12 22c4 0 7-2.7 7-6.4 0-3-1.6-5-4.4-7.8.2 2.6-1 4-2.4 4.8.2-3.5-1.5-5.5-3.6-7.6C7.2 12.2 5 14.6 5 17.1 5 19.8 7.8 22 12 22Z', geweld: 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10ZM12 8v4m0 4h.01', onveilig: 'M10.3 3.9 2.1 18a2 2 0 0 0 1.7 3h16.4a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0ZM12 9v4m0 4h.01', verkeer: 'M5 17h14M6 17 4 11l2-5h12l2 5-2 6M6 17v2m12-2v2M7.5 12h.01m9 0h.01', blokkade: 'M3 21h18M6 21V10l6-4 6 4v11M9 21v-5h6v5M4 10h16', weer: 'M17.5 19H9a7 7 0 1 1 6.7-9A5 5 0 1 1 17.5 19ZM8 19l-1 2m5-2-1 2m5-2-1 2', stoffen: 'M9 3h6m-5 0v7l-5.4 8.6A2 2 0 0 0 6.3 22h11.4a2 2 0 0 0 1.7-3.4L14 10V3M8 16h8', drukte: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8', dier: 'M11.5 16.5c-1.1 0-2 .9-2 2 0 1.7 1.4 2.5 2.5 2.5s2.5-.8 2.5-2.5c0-1.1-.9-2-2-2h-1Z', anders: 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20ZM9.1 9a3 3 0 1 1 5.7 1.4c-.6.8-1.5 1.2-2.2 1.8-.6.5-.6 1-.6 1.8M12 17h.01' } as Record<Incident['category'], string>)[category];
  return `<span class="leaflet-incident-marker" style="background:${markerColor(category)}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="${path}" /></svg></span>`;
}
function markerCoordinates(incident: Incident): [number, number] {
  if (incident.latitude !== undefined && incident.longitude !== undefined) return [incident.latitude, incident.longitude];
  return [focus.value[0] + (50 - incident.top) * 0.00012, focus.value[1] + (incident.left - 50) * 0.0002];
}
function mapPixel(latitude: number, longitude: number) {
  const point = worldPoint(latitude, longitude, zoom.value);
  const center = worldPoint(focus.value[0], focus.value[1], zoom.value);
  return { left: mapSize.value.width / 2 + point.x - center.x, top: mapSize.value.height / 2 + point.y - center.y };
}
function geometryPath(incident: Incident): Array<{ id: number; path: string; kind: 'line' | 'area' }> {
  const geometry = incident.geometry;
  if (!geometry || !['LineString', 'Polygon'].includes(geometry.type)) return [];
  const rings = geometry.type === 'Polygon' ? geometry.coordinates as number[][][] : [geometry.coordinates as number[][]];
  return rings.filter((ring) => Array.isArray(ring) && ring.length > 1).map((ring, index) => {
    const commands = ring.map((coordinate, pointIndex) => {
      const [longitude, latitude] = coordinate;
      const pixel = mapPixel(Number(latitude), Number(longitude));
      return `${pointIndex === 0 ? 'M' : 'L'}${pixel.left.toFixed(1)} ${pixel.top.toFixed(1)}`;
    });
    if (geometry.type === 'Polygon') commands.push('Z');
    return { id: incident.id * 100 + index, path: commands.join(' '), kind: geometry.type === 'Polygon' ? 'area' as const : 'line' as const };
  });
}
function markerStyle(incident: Incident) { const [latitude, longitude] = markerCoordinates(incident); const pixel = mapPixel(latitude, longitude); return { left: `${pixel.left}px`, top: `${pixel.top}px` }; }
const userMarkerStyle = computed(() => { if (!userPosition.value) return {}; const pixel = mapPixel(userPosition.value.coords.latitude, userPosition.value.coords.longitude); return { left: `${pixel.left}px`, top: `${pixel.top}px` }; });
function updateUserLocation(position: GeolocationPosition, recenter = true) { userPosition.value = position; if (recenter) focus.value = [position.coords.latitude, position.coords.longitude]; emit('location', position); }
function requestLocation(recenter = true) { navigator.geolocation?.getCurrentPosition((position) => updateUserLocation(position, recenter), () => undefined, { enableHighAccuracy: true, timeout: 7000, maximumAge: 0 }); }
function focusOn(latitude: number, longitude: number) { focus.value = [latitude, longitude]; requestLocation(false); }
onMounted(() => { const resize = () => { if (mapElement.value) mapSize.value = { width: mapElement.value.clientWidth, height: mapElement.value.clientHeight }; }; resizeObserver = new ResizeObserver(resize); if (mapElement.value) resizeObserver.observe(mapElement.value); resize(); if (props.initialFocus) focusOn(props.initialFocus.latitude, props.initialFocus.longitude); else requestLocation(); });
onBeforeUnmount(() => resizeObserver?.disconnect());
defineExpose({ requestLocation, focusOn });
</script>
