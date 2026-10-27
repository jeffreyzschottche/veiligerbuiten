<template>
  <AccountActionFrame :title="success ? copy.verifiedTitle : copy.verifyTitle">
    <p v-if="loading" role="status">{{ copy.verifying }}</p>
    <template v-else-if="success">
      <p role="status">{{ copy.verifiedBody }}</p>
      <NuxtLink to="/account" class="btn btn--brand btn--block">{{ copy.account }}</NuxtLink>
    </template>
    <template v-else>
      <p class="error-text" role="alert">{{ error }}</p>
      <NuxtLink to="/login" class="btn btn--ink btn--block">{{ copy.login }}</NuxtLink>
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
useHead(() => ({ title: `${copy.value.verifyTitle} · VeiligerBuiten`, meta: [{ name: 'robots', content: 'noindex' }, { name: 'referrer', content: 'no-referrer' }] }));
const loading = ref(true);
const success = ref(false);
const error = ref('');
onMounted(async () => {
  if (route.query.lang === 'nl' || route.query.lang === 'en') setLocale(route.query.lang);
  const { id, hash, expires, signature } = route.query;
  // Alleen onze eigen verificatieroute aanroepen; geen URL uit een queryparameter volgen.
  if (typeof id !== 'string' || !/^\d+$/.test(id)
    || typeof hash !== 'string' || !/^[a-f0-9]{40}$/.test(hash)
    || typeof expires !== 'string' || !/^\d+$/.test(expires)
    || typeof signature !== 'string' || !/^[a-f0-9]{64}$/.test(signature)) {
    error.value = copy.value.verifyInvalid;
    loading.value = false;
    return;
  }
  try {
    const query = new URLSearchParams({ expires, signature });
    await useApi().get(`/email/verify/${id}/${hash}?${query}`);
    success.value = true;
    if (auth.isLoggedIn) await auth.refreshAccount().catch(() => undefined);
    await navigateTo({ path: '/verify-email', query: {} }, { replace: true });
  } catch (caught: any) {
    error.value = [403, 404, 422].includes(caught?.status) ? copy.value.verifyInvalid : copy.value.verifyUnavailable;
  } finally {
    loading.value = false;
  }
});
</script>
