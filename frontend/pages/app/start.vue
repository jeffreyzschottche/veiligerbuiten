<template>
  <div class="vb-app start">
    <!-- Welkom -->
    <section v-if="step === 'welcome'" class="start__welcome">
      <div class="start__rings" aria-hidden="true"><i /><i /><i /></div>
      <div class="start__hero">
        <LogoMark :size="64" inverse />
        <h1>{{ t.start.slogan }}</h1>
        <p>{{ t.start.intro }}</p>
      </div>
      <div class="start__actions">
        <button class="btn btn--brand btn--block" @click="go('login')">{{ t.start.login }}</button>
        <!-- Registreren en betalen gaat via de website; in de app alleen de tekst, geen link. -->
        <p class="start__note start__note--dark">{{ t.start.noAccount }}</p>
        <div class="start__lang">
          <button :aria-pressed="locale === 'nl'" @click="setLocale('nl')">NL</button>
          <button :aria-pressed="locale === 'en'" @click="setLocale('en')">EN</button>
        </div>
      </div>
    </section>

    <!-- Account -->
    <section v-else-if="step === 'login'" class="start__form">
      <button class="icon-btn" :aria-label="t.common.back" @click="go('welcome')"><Icon name="back" /></button>
      <h1>{{ t.start.loginTitle }}</h1>
      <form @submit.prevent="submit">
        <label class="field">
          <span class="field__label">{{ t.start.email }}</span>
          <input v-model="form.email" class="input" type="email" inputmode="email" autocomplete="email" autocapitalize="off" required>
        </label>
        <label class="field">
          <span class="field__label">{{ t.start.password }}</span>
          <input v-model="form.password" class="input" type="password" autocomplete="current-password" required>
        </label>
        <p v-if="error" class="error-text">{{ error }}</p>
        <button class="btn btn--ink btn--block" :disabled="busy">{{ busy ? '…' : t.start.login }}</button>
      </form>
      <NuxtLink to="/forgot-password" class="start__forgot">{{ t.start.forgot }}</NuxtLink>
      <p class="start__note">{{ t.start.noAccount }}</p>
    </section>

    <!-- Rechten -->
    <section v-else class="start__form">
      <h1>{{ t.start.permTitle }}</h1>
      <div class="card group">
        <div class="row">
          <span class="row__icon"><Icon name="locate" :size="18" /></span>
          <span class="row__main"><span class="row__title">{{ t.start.location }}</span><span class="row__sub">{{ t.start.locationBody }}</span></span>
          <button class="btn btn--sm" :class="permissions.location ? 'btn--soft' : 'btn--brand'" :disabled="permissions.location" @click="allowLocation">{{ permissions.location ? t.start.allowed : t.start.allow }}</button>
        </div>
        <div v-if="device.native" class="row">
          <span class="row__icon"><Icon name="bell" :size="18" /></span>
          <span class="row__main"><span class="row__title">{{ t.start.notifications }}</span><span class="row__sub">{{ t.start.notificationsBody }}</span></span>
          <button class="btn btn--sm" :class="permissions.notifications ? 'btn--soft' : 'btn--brand'" :disabled="permissions.notifications" @click="allowNotifications">{{ permissions.notifications ? t.start.allowed : t.start.allow }}</button>
        </div>
      </div>
      <button class="btn btn--ink btn--block" @click="finish">{{ permissions.location ? t.start.continue : t.start.skip }}</button>
    </section>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: false });
useHead({ title: 'VeiligerBuiten', meta: [{ name: 'theme-color', content: '#0b0d12' }] });

type Step = 'welcome' | 'login' | 'permissions';
const route = useRoute();
const { t, locale } = useAppCopy();
const { setLocale } = useLocale();
const auth = useAuthStore();
const device = useDevice();

const step = ref<Step>(auth.isLoggedIn ? 'permissions' : route.query.mode === 'login' ? 'login' : 'welcome');
const form = reactive({ email: '', password: '' });
const busy = ref(false);
const error = ref<string | null>(null);
const permissions = reactive({ location: false, notifications: false });

onMounted(async () => {
  permissions.location = (await device.locationPermission()) === 'granted';
  permissions.notifications = (await device.notificationPermission()) === 'granted';
});

function go(next: Step) {
  error.value = null;
  step.value = next;
}

async function submit() {
  busy.value = true;
  error.value = null;
  try {
    await auth.login(form.email.trim(), form.password);
    await device.link();
    step.value = 'permissions';
  } catch (caught: any) {
    error.value = caught?.data?.errors ? Object.values(caught.data.errors).flat()[0] as string : caught?.data?.message ?? t.value.errors.generic;
  } finally {
    busy.value = false;
  }
}

async function allowLocation() {
  permissions.location = await device.enableLocation();
}

async function allowNotifications() {
  permissions.notifications = await device.enableNotifications();
}

function finish() {
  navigateTo('/app', { replace: true });
}
</script>

<style scoped>
.start { overflow-y: auto; background: var(--vb-paper); }
.start__welcome { position: relative; display: flex; flex-direction: column; justify-content: space-between; min-height: 100%; overflow: hidden; padding: calc(var(--safe-top) + 64px) 24px calc(var(--safe-bottom) + 24px); color: #fff; background: var(--vb-ink); }
.start__rings { position: absolute; top: 18%; left: 50%; width: 0; height: 0; }
.start__rings i { position: absolute; top: 50%; left: 50%; border: 1px solid #ffffff14; border-radius: 50%; transform: translate(-50%, -50%); }
.start__rings i:nth-child(1) { width: 260px; height: 260px; }
.start__rings i:nth-child(2) { width: 480px; height: 480px; }
.start__rings i:nth-child(3) { width: 720px; height: 720px; }
.start__hero { position: relative; display: flex; flex-direction: column; align-items: center; text-align: center; }
.start__hero h1 { margin: 28px 0 14px; font-size: 56px; font-weight: 720; letter-spacing: -.055em; line-height: 1; }
.start__hero p { max-width: 320px; margin: 0; color: #a8aebb; font-size: 17px; line-height: 1.45; }
.start__actions { position: relative; display: grid; gap: 6px; }
.start__note { margin: 18px 8px 0; color: var(--vb-muted); font-size: 14px; line-height: 1.45; text-align: center; }
.start__note--dark { margin-top: 10px; color: #a8aebb; }
.start__lang { display: flex; justify-content: center; gap: 4px; margin-top: 6px; }
.start__lang button { min-width: 44px; min-height: 32px; border: 0; border-radius: 9px; background: none; color: #7d8492 !important; font-size: 13px; font-weight: 600; }
.start__lang button[aria-pressed='true'] { color: #fff !important; background: #ffffff14; }
.start__form { max-width: 440px; margin: 0 auto; padding: calc(var(--safe-top) + 20px) 20px calc(var(--safe-bottom) + 24px); }
.start__form h1 { margin: 24px 4px 24px; font-size: 32px; font-weight: 700; letter-spacing: -.04em; }
.start__form form { margin-bottom: 8px; }
.start__form .btn--ink { margin-top: 8px; }
.start__forgot { display: block; margin-top: 4px; color: var(--vb-muted); font-size: 14px; text-align: center; }
.start .row { align-items: flex-start; }
.start .row .btn { margin-top: 2px; }
</style>
