<template>
  <section class="screen">
    <h1 class="screen__title">{{ t.places.title }}</h1>
    <p class="screen__intro">{{ t.places.intro }}</p>

    <div v-if="places.length" class="card group">
      <div v-for="place in places" :key="place.id" class="row">
        <button class="place" @click="edit(place)">
          <span class="row__icon"><Icon name="pin" :size="18" /></span>
          <span class="row__main">
            <span class="row__title">{{ place.name }}</span>
            <span class="row__sub">{{ t.places.radius }} {{ distance(place.radius_m) }}<template v-if="counts[place.id]"> · <b class="place__live">{{ countText(counts[place.id]!) }}</b></template></span>
          </span>
        </button>
        <button class="switch" role="switch" :aria-checked="place.alerts_enabled" :aria-label="`${t.places.alerts} ${place.name}`" @click="toggle(place)" />
      </div>
    </div>
    <div v-else class="empty card">
      <span class="empty__icon"><Icon name="pin" :size="26" /></span>
      <h2>{{ t.places.empty }}</h2>
      <p>{{ t.places.emptyBody }}</p>
    </div>

    <button v-if="places.length < MAX_PLACES" class="btn btn--ink btn--block" @click="create"><Icon name="plus" :size="18" />{{ t.places.add }}</button>
    <p v-else class="group__help">{{ fill(t.places.limit, { max: MAX_PLACES }) }}</p>

    <PlaceSheet :open="editing !== null" :place="editing" :map-center="mapCenter" @close="editing = null" />
  </section>
</template>

<script setup lang="ts">
import type { Incident, Place } from '~/types/Incident';
import type { Position } from '~/composables/useDevice';
import { MAX_PLACES } from '~/composables/usePlaces';
import { distanceMeters } from '~/composables/useGeo';

const props = defineProps<{ mapCenter: Position | null; incidents: Incident[] }>();
const { t, fill, distance } = useAppCopy();
const { places, toggle } = usePlaces();
const editing = ref<Partial<Place> | null>(null);

/** Hoeveel situaties nu binnen bereik van elke plek liggen. */
const counts = computed(() => Object.fromEntries(places.value.map((place) => [
  place.id,
  props.incidents.filter((incident) => distanceMeters(place.latitude, place.longitude, incident.latitude, incident.longitude) <= place.radius_m + incident.alertRadius).length,
])));

function countText(n: number) {
  return n === 1 ? t.value.map.countOne : fill(t.value.map.count, { n });
}
function edit(place: Place) { editing.value = { ...place }; }
function create() { editing.value = {}; }
</script>

<style scoped>
.place { display: flex; flex: 1; align-items: center; gap: 12px; min-width: 0; padding: 0; border: 0; background: none; text-align: left; }
.place__live { color: var(--vb-warn); font-weight: 600; }
.empty { margin-bottom: 18px; padding: 32px 24px; text-align: center; }
.empty__icon { display: inline-grid; place-items: center; width: 56px; height: 56px; margin-bottom: 12px; border-radius: 18px; color: var(--vb-brand); background: var(--vb-brand-50); }
.empty h2 { margin: 0 0 6px; font-size: 19px; letter-spacing: -.02em; }
.empty p { margin: 0; color: var(--vb-muted); font-size: 15px; line-height: 1.45; }
</style>
