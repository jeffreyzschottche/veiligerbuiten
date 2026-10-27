<template>
  <aside v-if="auth.account?.email_verified === false" class="email-notice" aria-live="polite">
    <strong>{{ copy.verifyNotice }}</strong>
    <p>{{ copy.verifyNoticeBody.replace('{email}', auth.account.email) }}</p>
    <p v-if="sent" role="status">{{ copy.resent }}</p>
    <p v-if="error" class="error-text">{{ error }}</p>
    <button v-if="!sent" type="button" class="btn btn--soft btn--sm" :disabled="busy" @click="resend">{{ busy ? copy.sending : copy.resend }}</button>
  </aside>
</template>

<script setup lang="ts">
import { accountCopy } from '~/locales/account';
const auth = useAuthStore();
const { locale } = useLocale();
const copy = computed(() => accountCopy[locale.value]);
const sent = ref(false);
const busy = ref(false);
const error = ref('');
async function resend() {
  busy.value = true;
  error.value = '';
  try {
    await auth.resendVerification();
    sent.value = true;
    await auth.refreshAccount().catch(() => undefined);
  } catch (caught: any) {
    error.value = caught?.status === 429 ? copy.value.tooMany : copy.value.generic;
  } finally {
    busy.value = false;
  }
}
</script>

<style scoped>
.email-notice { margin-bottom: 24px; padding: 16px; border: 1px solid #2f6bff33; border-radius: 16px; background: #2f6bff0a; }
.email-notice strong { font-size: 15px; }
.email-notice p { margin: 8px 0 12px; color: var(--vb-muted); font-size: 14px; line-height: 1.5; overflow-wrap: anywhere; }
.email-notice button { white-space: normal; }
</style>
