<template>
  <BottomSheet :open="open" :title="place?.id ? t.places.edit : t.places.new" @close="emit('close')">
    <label class="field">
      <span class="field__label">{{ t.places.name }}</span>
      <input v-model="name" class="input" maxlength="40" :placeholder="t.places.namePlaceholder">
    </label>
    <div v-if="!place?.id" class="chips group">
      <button v-for="suggestion in t.places.suggestions" :key="suggestion" class="chip" :aria-pressed="name === suggestion" @click="name = suggestion">{{ suggestion }}</button>
    </div>

    <div class="group">
      <p class="group__label">{{ t.places.radius }}</p>
      <div class="segment">
        <button v-for="step in RADIUS_STEPS" :key="step" :aria-pressed="radius === step" @click="radius = step">{{ distance(step) }}</button>
      </div>
    </div>

    <div class="group">
      <p class="group__label">{{ t.places.location }}</p>
      <div class="segment">
        <button :aria-pressed="source === 'here'" @click="useHere">{{ t.places.useCurrent }}</button>
        <button :aria-pressed="source === 'map'" :disabled="!mapCenter" @click="useMap">{{ t.places.useMap }}</button>
      </div>
    </div>

    <div class="card group">
      <div class="row">
        <span class="row__main"><span class="row__title">{{ t.places.alerts }}</span></span>
        <button class="switch" role="switch" :aria-checked="alerts" :aria-label="t.places.alerts" @click="alerts = !alerts" />
      </div>
    </div>

    <p v-if="error" class="error-text">{{ error }}</p>
    <button class="btn btn--ink btn--block" :disabled="saving || !name.trim() || !point" @click="save">{{ t.common.save }}</button>
    <button v-if="place?.id" class="btn btn--ghost btn--block" :disabled="saving" @click="remove">{{ t.common.delete }}</button>
  </BottomSheet>
</template>

<script setup lang="ts">
import type { Place } from '~/types/Incident';
import { RADIUS_STEPS } from '~/composables/usePlaces';
import { currentPosition, type Position } from '~/composables/useDevice';

const props = defineProps<{ open: boolean; place: Partial<Place> | null; mapCenter: Position | null }>();
const emit = defineEmits<{ close: [] }>();
const { t, distance } = useAppCopy();
const places = usePlaces();

const name = ref('');
const radius = ref(500);
const alerts = ref(true);
const source = ref<'here' | 'map' | 'saved'>('here');
const point = ref<{ latitude: number; longitude: number } | null>(null);
const saving = ref(false);
const error = ref<string | null>(null);

watch(() => props.open, async (open) => {
  if (!open) return;
  error.value = null;
  name.value = props.place?.name ?? '';
  radius.value = props.place?.radius_m ?? 500;
  alerts.value = props.place?.alerts_enabled ?? true;
  if (props.place?.latitude !== undefined && props.place.longitude !== undefined) {
    source.value = 'saved';
    point.value = { latitude: props.place.latitude, longitude: props.place.longitude };
  } else {
    await useHere();
  }
});

async function useHere() {
  source.value = 'here';
  try {
    const here = await currentPosition(true);
    point.value = { latitude: here.latitude, longitude: here.longitude };
  } catch {
    error.value = t.value.errors.location;
    if (props.mapCenter) useMap();
  }
}

function useMap() {
  if (!props.mapCenter) return;
  source.value = 'map';
  point.value = { latitude: props.mapCenter.latitude, longitude: props.mapCenter.longitude };
}

async function save() {
  if (!point.value) return;
  saving.value = true;
  error.value = null;
  try {
    await places.save({ id: props.place?.id, name: name.value.trim(), ...point.value, radius_m: radius.value, alerts_enabled: alerts.value });
    emit('close');
  } catch (caught: any) {
    error.value = caught?.data?.errors ? Object.values(caught.data.errors).flat()[0] as string : t.value.errors.generic;
  } finally {
    saving.value = false;
  }
}

async function remove() {
  if (!props.place?.id) return;
  saving.value = true;
  try {
    await places.remove(props.place as Place);
    emit('close');
  } catch {
    error.value = t.value.errors.generic;
  } finally {
    saving.value = false;
  }
}
</script>

<style scoped>
.segment button { padding: 0 4px; font-size: 13px; }
.segment button:disabled { opacity: .4; }
</style>
