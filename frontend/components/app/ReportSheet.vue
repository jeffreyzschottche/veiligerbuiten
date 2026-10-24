<template>
  <BottomSheet :open="open" :title="title" :back="step === 'subtype' || step === 'confirm'" @close="close" @back="back">
    <!-- 1. Wat zie je? -->
    <template v-if="step === 'category'">
      <p class="quota" :class="{ 'is-empty': quota && quota.remaining === 0 }">
        <span class="quota__dots"><i v-for="n in quota?.limit ?? 3" :key="n" :class="{ used: n <= (quota?.used ?? 0) }" /></span>
        {{ quota && quota.remaining === 0 ? fill(t.report.none, { max: quota.limit }) : fill(t.report.left, { n: quota?.remaining ?? 3, max: quota?.limit ?? 3 }) }}
      </p>
      <div class="cats">
        <button v-for="category in categories" :key="category.value" class="cat" :disabled="quota?.remaining === 0" @click="pickCategory(category.value)">
          <Icon :name="category.icon" :size="22" />
          <span>{{ category[locale] }}</span>
        </button>
      </div>
    </template>

    <!-- 2. Wat precies? -->
    <div v-else-if="step === 'subtype' && category" class="card">
      <button v-for="[nl, en] in subtypes[category]" :key="nl" class="row" @click="pickSubtype(nl)">
        <span class="row__main"><span class="row__title">{{ locale === 'nl' ? nl : en }}</span></span>
        <Icon name="chevron" :size="16" class="row__end" />
      </button>
    </div>

    <!-- 3. Waar, en plaatsen -->
    <template v-else-if="step === 'confirm'">
      <div class="group">
        <p class="group__label">{{ t.report.where }}</p>
        <div class="segment">
          <button :aria-pressed="where === 'here'" @click="where = 'here'">{{ t.report.here }}</button>
          <button :aria-pressed="where === 'map'" :disabled="!mapAllowed" @click="where = 'map'">{{ t.report.mapCenter }}</button>
        </div>
        <p v-if="!mapAllowed && mapCenter" class="group__help">{{ t.report.tooFar }}</p>
      </div>
      <label class="field">
        <span class="field__label">{{ needsNote ? t.report.noteRequired : t.report.note }}</span>
        <textarea v-model="note" class="input" maxlength="180" :placeholder="t.report.notePlaceholder" />
      </label>
      <p v-if="error" class="error-text">{{ error }}</p>
      <button class="btn btn--ink btn--block" :disabled="submitting || (needsNote && !note.trim())" @click="submit">
        {{ submitting ? '…' : t.report.submit }}
      </button>
    </template>

    <!-- 4. Geplaatst -->
    <div v-else-if="step === 'done'" class="done">
      <span class="done__mark"><Icon name="check" :size="28" :stroke="2.6" /></span>
      <h3>{{ t.report.placed }}</h3>
      <p>{{ resultText }}</p>
      <p v-if="quota" class="done__quota">{{ fill(t.report.used, { n: quota.used, max: quota.limit }) }}</p>
      <button class="btn btn--ink btn--block" @click="close">{{ t.common.done }}</button>
    </div>
  </BottomSheet>
</template>

<script setup lang="ts">
import { Haptics, NotificationType } from '@capacitor/haptics';
import type { Incident, IncidentCategory, ReportQuota } from '~/types/Incident';
import { categories, subtypes, categoryOf } from '~/utils/taxonomy';
import { distanceMeters } from '~/composables/useGeo';
import { currentPosition, type Position } from '~/composables/useDevice';

const props = defineProps<{ open: boolean; mapCenter: Position | null }>();
const emit = defineEmits<{ close: []; placed: [incident: Incident] }>();
const { t, locale, fill } = useAppCopy();
const auth = useAuthStore();

type Step = 'category' | 'subtype' | 'confirm' | 'done';
const step = ref<Step>('category');
const category = ref<IncidentCategory | null>(null);
const subtype = ref<string | null>(null);
const where = ref<'here' | 'map'>('here');
const note = ref('');
const error = ref<string | null>(null);
const submitting = ref(false);
const here = ref<Position | null>(null);
const result = ref<'placed' | 'merged' | 'review'>('placed');
const quota = computed<ReportQuota | null>(() => auth.account?.report_quota ?? null);

const title = computed(() => ({ category: t.value.report.title, subtype: category.value ? categoryOf(category.value)[locale.value] : '', confirm: subtypeLabel.value, done: '' })[step.value]);
const subtypeLabel = computed(() => {
  const match = category.value ? subtypes[category.value].find(([nl]) => nl === subtype.value) : null;
  return match ? (locale.value === 'nl' ? match[0] : match[1]) : '';
});
const needsNote = computed(() => category.value === 'anders');
const mapAllowed = computed(() => !!(props.mapCenter && here.value && distanceMeters(here.value.latitude, here.value.longitude, props.mapCenter.latitude, props.mapCenter.longitude) <= 950));
const resultText = computed(() => ({ placed: t.value.report.placedBody, merged: t.value.report.merged, review: t.value.report.review })[result.value]);

watch(() => props.open, (open) => {
  if (!open) return;
  step.value = 'category';
  category.value = null;
  subtype.value = null;
  note.value = '';
  error.value = null;
  where.value = 'here';
  auth.refreshAccount().catch(() => undefined);
});

function pickCategory(value: IncidentCategory) {
  category.value = value;
  step.value = value === 'anders' ? 'confirm' : 'subtype';
  if (value === 'anders') subtype.value = subtypes.anders[0]?.[0] ?? 'Andere gevaarlijke situatie';
  locate();
}

function pickSubtype(value: string) {
  subtype.value = value;
  step.value = 'confirm';
}

function back() {
  error.value = null;
  step.value = step.value === 'confirm' && category.value !== 'anders' ? 'subtype' : 'category';
}

async function locate() {
  try {
    here.value = await currentPosition(true);
  } catch {
    here.value = null;
  }
}

async function submit() {
  if (!category.value || !subtype.value) return;
  submitting.value = true;
  error.value = null;
  try {
    if (!here.value || here.value.accuracy > 250) await locate();
    if (!here.value) throw new Error(t.value.errors.location);
    if (here.value.accuracy > 250) throw new Error(t.value.errors.accuracy);
    const point = where.value === 'map' && mapAllowed.value ? props.mapCenter! : here.value;
    await useDevice().ensureInstallation();
    const response = await useApi().post<{ data: Incident; duplicate: boolean; visibility: string; quota: ReportQuota }>('/reports', {
      category: category.value,
      subtype: subtype.value,
      description: note.value.trim() || null,
      latitude: point.latitude,
      longitude: point.longitude,
      user_latitude: here.value.latitude,
      user_longitude: here.value.longitude,
      location_accuracy_m: Math.round(here.value.accuracy),
    });
    result.value = response.visibility === 'review' ? 'review' : response.duplicate ? 'merged' : 'placed';
    if (auth.account) auth.account.report_quota = response.quota;
    if (response.visibility === 'public') emit('placed', response.data);
    step.value = 'done';
    Haptics.notification({ type: NotificationType.Success }).catch(() => undefined);
  } catch (caught: any) {
    error.value = caught?.data?.errors ? Object.values(caught.data.errors).flat()[0] as string : caught?.data?.message ?? caught?.message ?? t.value.errors.generic;
    if (caught?.status === 429) auth.refreshAccount().catch(() => undefined);
  } finally {
    submitting.value = false;
  }
}

function close() {
  emit('close');
}
</script>

<style scoped>
.quota { display: flex; align-items: center; gap: 10px; margin: 0 0 14px; color: var(--vb-muted); font-size: 14px; }
.quota.is-empty { color: var(--vb-danger); }
.quota__dots { display: flex; gap: 4px; }
.quota__dots i { width: 8px; height: 8px; border-radius: 50%; background: var(--vb-ink); }
.quota__dots i.used { background: var(--vb-line); }
.cats { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.cat { display: flex; align-items: center; gap: 12px; min-height: 64px; padding: 0 16px; border: 0; border-radius: 18px; background: #fff; box-shadow: inset 0 0 0 1px var(--vb-line-2); font-size: 15px; font-weight: 580; letter-spacing: -.01em; text-align: left; transition: transform .12s var(--vb-ease); }
.cat:active { transform: scale(.97); background: var(--vb-brand-50); }
.cat:disabled { opacity: .4; }
.cat :deep(svg) { color: var(--vb-ink-3); }
.done { padding: 8px 0 0; text-align: center; }
.done__mark { display: inline-grid; place-items: center; width: 64px; height: 64px; margin-bottom: 14px; border-radius: 50%; color: #fff; background: var(--vb-ok); }
.done h3 { margin: 0 0 6px; font-size: 24px; letter-spacing: -.03em; }
.done p { margin: 0 auto 8px; max-width: 300px; color: var(--vb-muted); line-height: 1.45; }
.done .done__quota { margin-bottom: 22px; font-family: var(--vb-mono); font-size: 13px; }
</style>
