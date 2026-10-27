<template>
  <AccountActionFrame :title="sent ? copy.sentTitle : copy.forgotTitle">
    <p v-if="sent" role="status">{{ copy.sentBody }}</p>
    <template v-else>
      <p>{{ copy.forgotIntro }}</p>
      <form @submit.prevent="submit">
        <label class="field"><span class="field__label">{{ copy.email }}</span><input v-model="email" class="input" type="email" autocomplete="email" autocapitalize="off" required></label>
        <p v-if="error" class="error-text" role="alert">{{ error }}</p>
        <button class="btn btn--brand btn--block" :disabled="busy">{{ busy ? copy.sending : copy.sendLink }}</button>
      </form>
    </template>
    <NuxtLink to="/login" class="account-action__link">{{ copy.login }}</NuxtLink>
  </AccountActionFrame>
</template>

<script setup lang="ts">
import { accountCopy } from '~/locales/account';
definePageMeta({ layout: false });
const { locale } = useLocale();
const copy = computed(() => accountCopy[locale.value]);
useHead(() => ({ title: `${copy.value.forgotTitle} · VeiligerBuiten`, meta: [{ name: 'robots', content: 'noindex' }] }));
const auth = useAuthStore();
const email = ref('');
const error = ref('');
const sent = ref(false);
const busy = ref(false);
async function submit() {
  error.value = '';
  busy.value = true;
  try {
    await auth.forgotPassword(email.value.trim());
    sent.value = true;
  } catch (caught: any) {
    error.value = caught?.status === 429 ? copy.value.tooMany : copy.value.generic;
  } finally {
    busy.value = false;
  }
}
</script>
