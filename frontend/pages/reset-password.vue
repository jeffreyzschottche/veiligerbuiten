<template>
  <AccountActionFrame :title="done ? copy.resetDoneTitle : copy.resetTitle">
    <template v-if="done">
      <p role="status">{{ copy.resetDoneBody }}</p>
      <NuxtLink to="/login" class="btn btn--brand btn--block">{{ copy.login }}</NuxtLink>
    </template>
    <template v-else>
      <p v-if="!validLink" role="alert">{{ copy.resetInvalid }}</p>
      <template v-else>
        <p>{{ copy.resetIntro }}</p>
        <form @submit.prevent="submit">
          <label class="field"><span class="field__label">{{ copy.email }}</span><input v-model="form.email" class="input" type="email" autocomplete="email" required></label>
          <label class="field"><span class="field__label">{{ copy.password }}</span><input v-model="form.password" class="input" type="password" autocomplete="new-password" minlength="8" required><span class="group__help">{{ copy.passwordHint }}</span></label>
          <label class="field"><span class="field__label">{{ copy.confirmPassword }}</span><input v-model="form.password_confirmation" class="input" type="password" autocomplete="new-password" minlength="8" required></label>
          <p v-if="error" class="error-text" role="alert">{{ error }}</p>
          <button class="btn btn--brand btn--block" :disabled="busy">{{ busy ? copy.resetting : copy.resetButton }}</button>
        </form>
      </template>
      <NuxtLink to="/forgot-password" class="account-action__link">{{ copy.requestNew }}</NuxtLink>
    </template>
  </AccountActionFrame>
</template>

<script setup lang="ts">
import { accountCopy } from '~/locales/account';
definePageMeta({ layout: false });
const route = useRoute();
const auth = useAuthStore();
const { locale, setLocale } = useLocale();
const copy = computed(() => accountCopy[locale.value]);
useHead(() => ({ title: `${copy.value.resetTitle} · VeiligerBuiten`, meta: [{ name: 'robots', content: 'noindex' }, { name: 'referrer', content: 'no-referrer' }] }));
const form = reactive({
  token: typeof route.query.token === 'string' ? route.query.token : '',
  email: typeof route.query.email === 'string' ? route.query.email : '',
  password: '', password_confirmation: '',
});
const validLink = computed(() => !!form.token && form.token.length <= 255);
const error = ref('');
const done = ref(false);
const busy = ref(false);
onMounted(() => {
  if (route.query.lang === 'nl' || route.query.lang === 'en') setLocale(route.query.lang);
});
async function submit() {
  error.value = '';
  if (form.password !== form.password_confirmation) {
    error.value = locale.value === 'nl' ? 'De wachtwoorden komen niet overeen.' : 'The passwords do not match.';
    return;
  }
  busy.value = true;
  try {
    await auth.resetPassword(form.token, form.email.trim(), form.password, form.password_confirmation);
    await auth.clear();
    form.password = form.password_confirmation = '';
    done.value = true;
    await navigateTo({ path: '/reset-password', query: {} }, { replace: true });
  } catch (caught: any) {
    error.value = caught?.status === 429 ? copy.value.tooMany : caught?.status === 422 ? copy.value.resetInvalid : copy.value.generic;
  } finally {
    busy.value = false;
  }
}
</script>
