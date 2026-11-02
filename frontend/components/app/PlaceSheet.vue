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
      <p class="group__label">{{ t.places.location }}</p>
      <PlacePicker v-model:point="point" :radius="radius" :color="color" @locate="useHere" />
      <p class="group__help">{{ t.places.pickerHelp }}</p>
    </div>

    <div class="group">
      <p class="group__label">{{ t.places.radius }}</p>
      <div class="segment">
        <button v-for="step in RADIUS_STEPS" :key="step" :aria-pressed="radius === step" @click="radius = step">{{ distance(step) }}</button>
      </div>
    </div>

    <div class="group">
      <p class="group__label">{{ t.places.color }}</p>
      <div class="swatches" role="radiogroup" :aria-label="t.places.color">
        <button
          v-for="(hex, key) in PLACE_COLORS"
          :key="key"
          class="swatch"
          role="radio"
          :aria-checked="color === key"
          :aria-label="t.places.colors[key]"
          :title="t.places.colors[key]"
          :style="{ '--swatch': hex }"
          @click="color = key"
        ><Icon v-if="color === key" name="check" :size="16" :stroke="3" /></button>
      </div>
    </div>

    <p v-if="error" class="error-text">{{ error }}</p>

    <template #footer>
      <!-- Verwijderen in twee stappen, zonder browserdialoog. -->
      <div v-if="confirming" class="confirm">
        <p>{{ fill(t.places.removeConfirm, { name: place?.name ?? '' }) }}</p>
        <div class="actions">
          <button class="btn btn--soft actions__grow" :disabled="saving" @click="confirming = false">{{ t.common.cancel }}</button>
          <button class="btn btn--danger actions__grow" :disabled="saving" @click="remove"><Icon name="trash" :size="18" />{{ t.places.removeYes }}</button>
        </div>
      </div>
      <div v-else class="actions">
        <label class="alerts">
          <Icon name="bell" :size="18" />
          <span>{{ t.places.alerts }}</span>
          <button class="switch" role="switch" :aria-checked="alerts" :aria-label="t.places.alerts" @click="alerts = !alerts" />
        </label>
        <button v-if="place?.id" class="btn btn--danger actions__icon" :disabled="saving" :aria-label="t.places.remove" :title="t.places.remove" @click="confirming = true"><Icon name="trash" :size="20" /></button>
        <button class="btn btn--ink actions__grow" :disabled="saving || !name.trim() || !point" @click="save">{{ t.common.save }}</button>
      </div>
    </template>
  </BottomSheet>
</template>

<script setup lang="ts">
import type { Place } from '~/types/Incident';
import { RADIUS_STEPS } from '~/composables/usePlaces';
import { currentPosition, type Position } from '~/composables/useDevice';
import { PLACE_COLORS, type PlaceColor } from '~/utils/placeColors';

const props = defineProps<{ open: boolean; place: Partial<Place> | null; mapCenter: Position | null }>();
const emit = defineEmits<{ close: [] }>();
const { t, fill, distance } = useAppCopy();
const places = usePlaces();

const name = ref('');
const radius = ref(500);
const color = ref<PlaceColor>('ink');
const alerts = ref(true);
const point = ref<{ latitude: number; longitude: number } | null>(null);
const saving = ref(false);
const confirming = ref(false);
const error = ref<string | null>(null);

watch(() => props.open, async (open) => {
  if (!open) return;
  error.value = null;
  confirming.value = false;
  name.value = props.place?.name ?? '';
  radius.value = props.place?.radius_m ?? 500;
  color.value = props.place?.color ?? 'ink';
  alerts.value = props.place?.alerts_enabled ?? true;
  if (props.place?.latitude !== undefined && props.place.longitude !== undefined) {
    point.value = { latitude: props.place.latitude, longitude: props.place.longitude };
  } else {
    point.value = props.mapCenter ? { latitude: props.mapCenter.latitude, longitude: props.mapCenter.longitude } : null;
    await useHere();
  }
}, { immediate: true });

async function useHere() {
  try {
    const here = await currentPosition(true);
    point.value = { latitude: here.latitude, longitude: here.longitude };
  } catch {
    if (!point.value) error.value = t.value.errors.location;
  }
}

async function save() {
  if (!point.value) return;
  saving.value = true;
  error.value = null;
  try {
    await places.save({ id: props.place?.id, name: name.value.trim(), ...point.value, radius_m: radius.value, color: color.value, alerts_enabled: alerts.value });
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
.swatches { display: flex; flex-wrap: wrap; gap: 12px; }
.swatch { display: grid; place-items: center; width: 40px; height: 40px; padding: 0; border: 0; border-radius: 50%; background: var(--swatch); color: #fff; box-shadow: 0 0 0 2px #fff, 0 0 0 3px transparent; transition: box-shadow .15s var(--vb-ease); }
.swatch[aria-checked='true'] { box-shadow: 0 0 0 2px #fff, 0 0 0 4px var(--swatch); }
.actions { display: flex; align-items: center; gap: 10px; }
.actions__grow { flex: 1; min-width: 0; padding: 0 12px; }
.actions__icon { width: 52px; padding: 0; flex: none; }
.alerts { display: flex; align-items: center; gap: 8px; height: 52px; padding: 0 10px 0 14px; border-radius: 16px; background: #fff; box-shadow: inset 0 0 0 1px var(--vb-line); color: var(--vb-ink-3); font-size: 14px; font-weight: 560; flex: none; }
/* Smalle telefoons: alleen bel + schakelaar, zodat Opslaan breed genoeg blijft. */
@media (max-width: 400px) { .alerts span { display: none; } }
.confirm p { margin: 0 0 12px; font-size: 15px; line-height: 1.45; }
</style>
