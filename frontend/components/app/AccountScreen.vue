<template>
  <section v-if="account" class="screen">
    <h1 class="screen__title">{{ t.account.title }}</h1>
    <p class="screen__intro">{{ account.name }} · {{ account.email }}</p>

    <!-- Meldingen -->
    <div class="group">
      <p class="group__label">{{ t.account.level }}</p>
      <div class="segment">
        <button v-for="level in levels" :key="level" :aria-pressed="settings.alert_filters.min_level === level" @click="setLevel(level)">{{ t.account.levelOptions[level] }}</button>
      </div>
      <p class="group__help">{{ t.account.levelHelp }}</p>
    </div>

    <div class="group">
      <p class="group__label">{{ t.account.categories }}</p>
      <div class="chips">
        <button class="chip" :aria-pressed="allCategories" @click="setAllCategories">{{ t.account.allCategories }}</button>
        <button v-for="category in categories" :key="category.value" class="chip" :aria-pressed="!allCategories && settings.alert_filters.categories.includes(category.value)" @click="toggleCategory(category.value)">
          <Icon :name="category.icon" :size="15" />{{ category[locale] }}
        </button>
      </div>
    </div>

    <div class="group">
      <p class="group__label">{{ t.account.interval }}</p>
      <div class="segment">
        <button v-for="minutes in [15, 30, 60]" :key="minutes" :aria-pressed="settings.check_interval_minutes === minutes" @click="save({ check_interval_minutes: minutes })">{{ fill(t.account.intervalOption, { n: minutes }) }}</button>
      </div>
      <p class="group__help">{{ t.account.intervalHelp }}</p>
    </div>

    <div class="group">
      <div class="card">
        <div class="row">
          <span class="row__icon"><Icon name="moon" :size="18" /></span>
          <span class="row__main"><span class="row__title">{{ t.account.quiet }}</span><span class="row__sub">{{ t.account.quietHelp }}</span></span>
          <button class="switch" role="switch" :aria-checked="settings.quiet_hours.enabled" :aria-label="t.account.quiet" @click="save({ quiet_hours: { ...settings.quiet_hours, enabled: !settings.quiet_hours.enabled } })" />
        </div>
        <div v-if="settings.quiet_hours.enabled" class="row quiet">
          <label>{{ t.account.from }} <input type="time" class="input input--time" :value="settings.quiet_hours.from" @change="setQuiet('from', $event)"></label>
          <label>{{ t.account.to }} <input type="time" class="input input--time" :value="settings.quiet_hours.to" @change="setQuiet('to', $event)"></label>
        </div>
      </div>
    </div>

    <!-- Taal -->
    <div class="group">
      <p class="group__label">{{ t.account.language }}</p>
      <div class="segment">
        <button :aria-pressed="locale === 'nl'" @click="save({ locale: 'nl' })">Nederlands</button>
        <button :aria-pressed="locale === 'en'" @click="save({ locale: 'en' })">English</button>
      </div>
    </div>

    <!-- AI -->
    <AiConnection class="group" />

    <!-- Abonnement -->
    <div class="group">
      <p class="group__label">{{ t.account.subscription }}</p>
      <div class="card">
        <div class="row">
          <span class="row__icon"><Icon name="card" :size="18" /></span>
          <span class="row__main">
            <span class="row__title">{{ account.subscription.active ? t.account.active : t.account.inactive }}</span>
            <span v-if="account.subscription.expires_at" class="row__sub">{{ fill(t.account.activeUntil, { date: new Date(account.subscription.expires_at).toLocaleDateString(locale === 'nl' ? 'nl-NL' : 'en-GB') }) }}</span>
          </span>
          <button v-if="billing.available" class="btn btn--soft btn--sm" @click="billing.manage()">{{ t.account.manage }}</button>
        </div>
        <button v-if="billing.available" class="row" :disabled="billing.busy.value" @click="billing.restore().catch(() => undefined)">
          <span class="row__main"><span class="row__title restore">{{ t.account.restore }}</span></span>
        </button>
      </div>
    </div>

    <div class="card group">
      <button class="row" @click="emit('logout')">
        <span class="row__icon"><Icon name="logout" :size="18" /></span>
        <span class="row__main"><span class="row__title">{{ t.account.logout }}</span></span>
      </button>
      <button class="row" @click="deleting = true">
        <span class="row__icon row__icon--danger"><Icon name="trash" :size="18" /></span>
        <span class="row__main"><span class="row__title danger">{{ t.account.deleteAccount }}</span></span>
      </button>
    </div>

    <p class="foot">
      <a href="https://veiligerbuiten.nl/privacy" target="_blank">{{ t.account.privacy }}</a> ·
      <a href="https://veiligerbuiten.nl/voorwaarden" target="_blank">{{ t.account.terms }}</a> ·
      <template v-if="ads.available"><a href="#" @click.prevent="ads.privacyOptions()">{{ t.account.adPrivacy }}</a> · </template>
      {{ fill(t.account.version, { v: version }) }}
    </p>

    <BottomSheet :open="deleting" :title="t.account.deleteAccount" @close="deleting = false">
      <p class="delete-text">{{ t.account.deleteConfirm }}</p>
      <label class="field">
        <span class="field__label">{{ t.start.password }}</span>
        <input v-model="password" type="password" class="input" autocomplete="current-password">
      </label>
      <p v-if="error" class="error-text">{{ error }}</p>
      <button class="btn btn--danger btn--block" :disabled="!password" @click="remove">{{ t.account.deleteAccount }}</button>
    </BottomSheet>
  </section>
</template>

<script setup lang="ts">
import type { AccountSettings } from '~/types/User';
import type { DangerLevel, IncidentCategory } from '~/types/Incident';
import { categories } from '~/utils/taxonomy';

const emit = defineEmits<{ logout: []; deleted: [] }>();
const { t, locale, fill } = useAppCopy();
const auth = useAuthStore();
const billing = useBilling();
const ads = useAds();
const { places } = usePlaces();
const version = useRuntimeConfig().public.appVersion;

const account = computed(() => auth.account);
const settings = computed(() => auth.account!.settings);
const levels: DangerLevel[] = ['danger', 'warn', 'info'];
const allCategories = computed(() => settings.value.alert_filters.categories.length >= categories.length);
const deleting = ref(false);
const password = ref('');
const error = ref<string | null>(null);

async function save(changes: Partial<AccountSettings> & { locale?: 'nl' | 'en' }) {
  // Direct tonen, daarna opslaan; bij een fout zet de server-versie het terug.
  if (auth.account) {
    const { locale: nextLocale, ...rest } = changes;
    auth.account.settings = { ...auth.account.settings, ...rest };
    if (nextLocale) useLocale().setLocale(nextLocale);
  }
  try {
    await auth.updateSettings(changes);
  } catch {
    await auth.refreshAccount().catch(() => undefined);
  }
  await configureRunner(places.value);
}

function setLevel(level: DangerLevel) {
  save({ alert_filters: { ...settings.value.alert_filters, min_level: level } });
}

function setAllCategories() {
  save({ alert_filters: { ...settings.value.alert_filters, categories: categories.map((category) => category.value) } });
}

function toggleCategory(value: IncidentCategory) {
  const current = allCategories.value ? [] : settings.value.alert_filters.categories;
  const next = current.includes(value) ? current.filter((item) => item !== value) : [...current, value];
  save({ alert_filters: { ...settings.value.alert_filters, categories: next.length ? next : categories.map((category) => category.value) } });
}

function setQuiet(key: 'from' | 'to', event: Event) {
  const value = (event.target as HTMLInputElement).value;
  if (/^\d{2}:\d{2}$/.test(value)) save({ quiet_hours: { ...settings.value.quiet_hours, [key]: value } });
}

async function remove() {
  error.value = null;
  try {
    await auth.deleteAccount(password.value);
    deleting.value = false;
    emit('deleted');
  } catch (caught: any) {
    error.value = caught?.data?.errors ? Object.values(caught.data.errors).flat()[0] as string : t.value.errors.generic;
  }
}
</script>

<style scoped>
.quiet { gap: 16px; }
.quiet label { display: flex; flex: 1; align-items: center; gap: 8px; color: var(--vb-muted); font-size: 14px; }
.input--time { min-height: 40px; padding: 0 10px; }
.restore { color: var(--vb-brand); }
.danger { color: var(--vb-danger); }
.row__icon--danger { color: var(--vb-danger); background: #fdecec; }
.foot { margin: 8px 0 0; color: var(--vb-subtle); font-size: 12px; text-align: center; }
.foot a { color: inherit; }
.delete-text { margin: 0 0 16px; color: var(--vb-muted); line-height: 1.45; }
</style>
