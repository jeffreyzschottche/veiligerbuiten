<template>
  <div class="vb-app signup">
    <header class="signup__top">
      <NuxtLink to="/" aria-label="VeiligerBuiten"><Logo inverse :size="30" /></NuxtLink>
      <div class="signup__lang">
        <button :aria-pressed="locale === 'nl'" @click="setLocale('nl')">NL</button>
        <button :aria-pressed="locale === 'en'" @click="setLocale('en')">EN</button>
      </div>
    </header>

    <main class="signup__card">
      <EmailVerificationNotice v-if="ready && (step === 'pay' || step === 'done')" />
      <section v-if="!ready" class="signup__center" aria-busy="true"><p>{{ t.common.loading }}</p></section>
      <!-- Terug van Stripe -->
      <section v-else-if="step === 'processing'" class="signup__center">
        <span class="signup__spinner" aria-hidden="true" />
        <p>{{ t.signup.processing }}</p>
      </section>

      <!-- Account maken of inloggen -->
      <section v-else-if="step === 'account'">
        <h1>{{ mode === 'signup' ? t.signup.title : t.signup.loginTitle }}</h1>
        <p class="signup__intro">{{ mode === 'signup' ? t.signup.intro : t.signup.loginIntro }}</p>
        <form @submit.prevent="submit">
          <label v-if="mode === 'signup'" class="field">
            <span class="field__label">{{ t.signup.name }}</span>
            <input v-model="form.name" class="input" autocomplete="given-name" required>
          </label>
          <label class="field">
            <span class="field__label">{{ t.start.email }}</span>
            <input v-model="form.email" class="input" type="email" inputmode="email" autocomplete="email" autocapitalize="off" required>
          </label>
          <label class="field">
            <span class="field__label">{{ t.start.password }}</span>
            <input v-model="form.password" class="input" type="password" :autocomplete="mode === 'signup' ? 'new-password' : 'current-password'" :minlength="mode === 'signup' ? 8 : undefined" required>
            <span v-if="mode === 'signup'" class="group__help">{{ t.signup.passwordHint }}</span>
          </label>
          <label v-if="mode === 'signup'" class="signup__terms">
            <input v-model="form.terms" type="checkbox" required>
            <span>
              <template v-for="(part, index) in termsParts" :key="index">
                <NuxtLink v-if="part === '{terms}'" to="/voorwaarden" target="_blank">{{ t.signup.termsLink }}</NuxtLink>
                <NuxtLink v-else-if="part === '{privacy}'" to="/privacy" target="_blank">{{ t.signup.privacyLink }}</NuxtLink>
                <template v-else>{{ part }}</template>
              </template>
            </span>
          </label>
          <p v-if="error" class="error-text">{{ error }}</p>
          <button class="btn btn--ink btn--block" :disabled="busy">{{ busy ? '…' : mode === 'signup' ? t.signup.createAndPay : t.start.login }}</button>
        </form>
        <NuxtLink :to="mode === 'signup' ? '/account' : '/aanmelden'" class="btn btn--ghost btn--block">{{ mode === 'signup' ? t.signup.toLogin : t.signup.toSignup }}</NuxtLink>
        <NuxtLink v-if="mode === 'login'" to="/forgot-password" class="signup__small">{{ t.start.forgot }}</NuxtLink>
      </section>

      <!-- Betalen -->
      <section v-else-if="step === 'pay'">
        <h1>{{ t.signup.payTitle }}</h1>
        <p class="signup__intro">{{ fill(t.signup.payIntro, { name: auth.account?.name ?? '' }) }}</p>
        <ul class="signup__points">
          <li v-for="point in t.paywall.points" :key="point"><Icon name="check" :size="18" :stroke="2.4" />{{ point }}</li>
        </ul>
        <p v-if="error" class="error-text">{{ error }}</p>
        <button class="btn btn--brand btn--block" :disabled="billing.busy.value" @click="pay">{{ billing.busy.value ? '…' : t.signup.pay }}</button>
        <p class="signup__fine">{{ t.signup.payFine }}</p>
        <button class="btn btn--ghost btn--block" @click="logout">{{ t.signup.otherAccount }}</button>
      </section>

      <!-- Klaar -->
      <section v-else>
        <span class="signup__done" aria-hidden="true"><Icon name="check" :size="28" :stroke="2.6" /></span>
        <h1>{{ t.signup.doneTitle }}</h1>
        <p class="signup__intro">{{ fill(t.signup.doneIntro, { email: auth.account?.email ?? '' }) }}</p>
        <StoreButtons :copy="siteCopy[locale]" class="signup__stores" />
        <NuxtLink to="/app" class="signup__small signup__web">{{ t.signup.openWeb }}</NuxtLink>
        <div class="card signup__status">
          <div class="row">
            <span class="row__icon"><Icon name="card" :size="18" /></span>
            <span class="row__main"><span class="row__title">{{ statusText }}</span></span>
            <button v-if="billing.manageTarget()" class="btn btn--soft btn--sm" :disabled="billing.busy.value" @click="manage">{{ t.signup.manage }}</button>
          </div>
        </div>
        <p v-if="error" class="error-text">{{ error }}</p>
        <button class="btn btn--ghost btn--block" @click="logout">{{ t.signup.otherAccount }}</button>
      </section>
    </main>
  </div>
</template>

<script setup lang="ts">
import { siteCopy } from '~/locales/site';

/**
 * Registreren en betalen op de website. De app logt alleen in; wie daar nog geen
 * account heeft, ziet de tekst dat dat op veiligerbuiten.nl kan. Ook /account: hier
 * beheer je je abonnement (opzeggen, betaalmethode, facturen) via Stripe.
 */
useHead({ title: 'Account · VeiligerBuiten', meta: [{ name: 'theme-color', content: '#0b0d12' }, { name: 'robots', content: 'noindex' }] });

const props = defineProps<{ mode: 'signup' | 'login' }>();
const route = useRoute();
const router = useRouter();
const { t, locale, fill } = useAppCopy();
const { setLocale } = useLocale();
const auth = useAuthStore();
const billing = useBilling();

const mode = computed(() => props.mode);
const form = reactive({ name: '', email: '', password: '', terms: false });
const ready = ref(false);
const busy = ref(false);
const processing = ref(typeof route.query.betaald === 'string');
const error = ref<string | null>(null);

const step = computed(() => {
  if (processing.value) return 'processing';
  if (!auth.isLoggedIn || !auth.account) return 'account';
  return auth.isSubscribed ? 'done' : 'pay';
});
const termsParts = computed(() => t.value.signup.terms.split(/(\{terms\}|\{privacy\})/).filter(Boolean));
const statusText = computed(() => {
  const subscription = auth.account?.subscription;
  const date = subscription?.expires_at ? new Date(subscription.expires_at).toLocaleDateString(locale.value === 'nl' ? 'nl-NL' : 'en-GB') : '';
  if (subscription?.status === 'cancelled') return fill(t.value.signup.cancelledUntil, { date });
  if (subscription?.status === 'gift') return fill(t.value.signup.gift, { date });
  if (subscription?.provider === 'app_store' || subscription?.provider === 'play_store') return fill(t.value.signup.store, { store: subscription.provider === 'app_store' ? 'App Store' : 'Google Play' });
  return t.value.signup.active;
});

onMounted(async () => {
  ready.value = true;
  if (auth.isLoggedIn) await auth.refreshAccount().catch(() => undefined);
  const session = route.query.betaald;
  if (typeof session !== 'string') return;
  router.replace({ query: {} });
  // De server vraagt Stripe zelf naar deze betaling; de webhook hoeft niet eerst binnen te zijn.
  for (let attempt = 0; attempt < 4 && auth.isLoggedIn; attempt++) {
    if (await billing.syncWithServer(session).catch(() => false)) break;
    await new Promise((resolve) => setTimeout(resolve, 2000));
  }
  processing.value = false;
  if (auth.isLoggedIn && !auth.isSubscribed) error.value = t.value.signup.notYet;
});

watch(mode, () => { error.value = null; });

async function submit() {
  busy.value = true;
  error.value = null;
  try {
    if (mode.value === 'signup') {
      await auth.register(form.name.trim(), form.email.trim(), form.password);
      await pay();
    } else {
      await auth.login(form.email.trim(), form.password);
    }
  } catch (caught: any) {
    error.value = caught?.data?.errors ? Object.values(caught.data.errors).flat()[0] as string : caught?.data?.message ?? t.value.errors.generic;
  } finally {
    busy.value = false;
  }
}

async function pay() {
  error.value = null;
  try {
    await billing.checkout();
  } catch {
    error.value = t.value.paywall.unavailable;
  }
}

async function manage() {
  error.value = null;
  try {
    await billing.manage();
  } catch {
    error.value = t.value.paywall.unavailable;
  }
}

async function logout() {
  error.value = null;
  await auth.logout();
}
</script>

<style scoped>
.signup { overflow-y: auto; padding: calc(var(--safe-top) + 20px) 16px calc(var(--safe-bottom) + 40px); background: var(--vb-ink); -webkit-user-select: auto; user-select: auto; }
.signup__top { display: flex; align-items: center; justify-content: space-between; max-width: 460px; margin: 0 auto 28px; color: #fff; }
.signup__top a { color: #fff; text-decoration: none; }
.signup__lang { display: flex; gap: 4px; }
.signup__lang button { min-width: 44px; min-height: 32px; border: 0; border-radius: 9px; background: none; color: #7d8492 !important; font-size: 13px; font-weight: 600; }
.signup__lang button[aria-pressed='true'] { color: #fff !important; background: #ffffff14; }
.signup__card { max-width: 460px; margin: 0 auto; padding: 28px 20px 18px; border-radius: 28px; background: var(--vb-paper); }
.signup__card h1 { margin: 0 4px 8px; font-size: 30px; font-weight: 700; letter-spacing: -.04em; }
.signup__intro { margin: 0 4px 22px; color: var(--vb-muted); font-size: 15px; line-height: 1.45; }
.signup__card form { margin-bottom: 6px; }
.signup__card .btn--ink, .signup__card .btn--brand { margin-top: 8px; }
.signup__terms { display: flex; align-items: flex-start; gap: 10px; margin: 4px 4px 14px; color: var(--vb-ink-3); font-size: 14px; line-height: 1.45; }
.signup__terms input { width: 20px; height: 20px; margin: 1px 0 0; flex: none; accent-color: var(--vb-brand); }
.signup__terms a, .signup__small { color: var(--vb-brand); }
.signup__small { display: block; margin-top: 4px; font-size: 14px; text-align: center; }
.signup__points { margin: 0 0 18px; padding: 0; list-style: none; }
.signup__points li { display: flex; align-items: center; gap: 12px; padding: 8px 4px; font-size: 16px; }
.signup__points li svg { color: var(--vb-brand); }
.signup__fine { margin: 12px 4px 4px; color: var(--vb-subtle); font-size: 12px; line-height: 1.45; text-align: center; }
.signup__done { display: grid; place-items: center; width: 56px; height: 56px; margin: 0 4px 18px; border-radius: 18px; color: #fff; background: var(--vb-brand); }
.signup__stores { margin: 0 4px 14px; }
.signup__web { margin: 0 0 22px; text-align: left; }
.signup__status { margin-bottom: 8px; }
.signup__center { display: flex; flex-direction: column; align-items: center; gap: 18px; padding: 40px 0; color: var(--vb-muted); text-align: center; }
.signup__spinner { width: 36px; height: 36px; border: 3px solid var(--vb-line); border-top-color: var(--vb-brand); border-radius: 50%; animation: signup-spin .8s linear infinite; }
@keyframes signup-spin { to { transform: rotate(360deg); } }
</style>
