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
      <section v-if="done">
        <span class="signup__done" aria-hidden="true"><Icon name="check" :size="28" :stroke="2.6" /></span>
        <h1>{{ t.remove.doneTitle }}</h1>
        <p class="signup__intro">{{ t.remove.doneIntro }}</p>
      </section>

      <section v-else>
        <h1>{{ t.remove.title }}</h1>
        <p class="signup__intro">{{ t.remove.intro }}</p>
        <h2>{{ t.remove.what }}</h2>
        <ul class="remove__points">
          <li v-for="point in t.remove.points" :key="point">{{ point }}</li>
        </ul>
        <form @submit.prevent="submit">
          <label v-if="!auth.isLoggedIn" class="field">
            <span class="field__label">{{ t.start.email }}</span>
            <input v-model="form.email" class="input" type="email" inputmode="email" autocomplete="email" autocapitalize="off" required>
          </label>
          <p v-else class="remove__account">{{ auth.account?.email }}</p>
          <label class="field">
            <span class="field__label">{{ t.start.password }}</span>
            <input v-model="form.password" class="input" type="password" autocomplete="current-password" required>
          </label>
          <p v-if="error" class="error-text">{{ error }}</p>
          <button class="btn btn--danger btn--block" :disabled="busy">{{ busy ? '…' : t.remove.confirm }}</button>
        </form>
        <p class="signup__fine">{{ t.remove.mail }}</p>
      </section>
    </main>
  </div>
</template>

<script setup lang="ts">
/** Account verwijderen vanaf de website: de stores eisen een link die zonder de app werkt. */
definePageMeta({ layout: false, alias: ['/delete-account'] });
useHead({ title: 'Account verwijderen · VeiligerBuiten', meta: [{ name: 'theme-color', content: '#0b0d12' }] });

const { t } = useAppCopy();
const { locale, setLocale } = useLocale();
const auth = useAuthStore();

const form = reactive({ email: '', password: '' });
const busy = ref(false);
const done = ref(false);
const error = ref<string | null>(null);

onMounted(() => {
  if (auth.isLoggedIn && !auth.account) auth.refreshAccount().catch(() => undefined);
});

async function submit() {
  busy.value = true;
  error.value = null;
  try {
    if (!auth.isLoggedIn) await auth.login(form.email.trim(), form.password);
    await auth.deleteAccount(form.password);
    done.value = true;
  } catch (caught: any) {
    error.value = caught?.data?.errors ? Object.values(caught.data.errors).flat()[0] as string : caught?.data?.message ?? t.value.errors.generic;
  } finally {
    busy.value = false;
  }
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
.signup__card h2 { margin: 0 4px 8px; font-size: 16px; font-weight: 650; }
.signup__intro { margin: 0 4px 22px; color: var(--vb-muted); font-size: 15px; line-height: 1.45; }
.signup__fine { margin: 14px 4px 4px; color: var(--vb-subtle); font-size: 12px; line-height: 1.45; text-align: center; }
.signup__done { display: grid; place-items: center; width: 56px; height: 56px; margin: 0 4px 18px; border-radius: 18px; color: #fff; background: var(--vb-brand); }
.signup__card .btn--danger { margin-top: 8px; }
.remove__points { margin: 0 4px 22px; padding-left: 18px; color: var(--vb-ink-3); font-size: 15px; line-height: 1.5; }
.remove__points li { margin-bottom: 6px; }
.remove__account { margin: 0 4px 12px; font-weight: 600; }
</style>
