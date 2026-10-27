<template>
  <section class="paywall">
    <div class="paywall__top">
      <LogoMark :size="56" inverse />
      <h1>{{ t.paywall.title }}</h1>
      <p class="paywall__slogan">{{ t.start.slogan }}</p>
    </div>

    <div class="paywall__card">
      <p class="paywall__price"><b>{{ price }}</b> <span>{{ t.paywall.per }}</span></p>
      <ul>
        <li v-for="point in t.paywall.points" :key="point"><Icon name="check" :size="18" :stroke="2.4" />{{ point }}</li>
      </ul>

      <p v-if="error" class="error-text">{{ error }}</p>
      <button class="btn btn--brand btn--block" :disabled="billing.busy.value || checking" @click="buy">{{ billing.busy.value ? t.paywall.checking : t.paywall.buy }}</button>
      <!-- In de app: herstellen uit de winkel. Op de website: terug van Stripe nog eens kijken. -->
      <button v-if="billing.native" class="btn btn--ghost btn--block" :disabled="billing.busy.value" @click="restore">{{ t.paywall.restore }}</button>
      <button v-else class="btn btn--ghost btn--block" :disabled="checking" @click="recheck">{{ checking ? t.paywall.checking : t.paywall.recheck }}</button>
      <p class="paywall__fine">{{ billing.native ? fill(t.paywall.fineStore, { store }) : t.paywall.fine }}</p>
      <p class="paywall__links"><NuxtLink to="/voorwaarden">{{ t.paywall.terms }}</NuxtLink> · <NuxtLink to="/privacy">{{ t.paywall.privacy }}</NuxtLink></p>
    </div>
    <button class="paywall__logout" @click="emit('logout')">{{ t.account.logout }}</button>
  </section>
</template>

<script setup lang="ts">
import { Capacitor } from '@capacitor/core';

const emit = defineEmits<{ subscribed: []; logout: [] }>();
const { t, fill } = useAppCopy();
const billing = useBilling();
const error = ref<string | null>(null);
const checking = ref(false);
const store = Capacitor.getPlatform() === 'ios' ? 'App Store' : 'Google Play';
/** De prijs zoals de winkel hem toont, in de valuta van de gebruiker; anders de vaste prijs. */
const price = computed(() => billing.monthly.value?.product.priceString ?? t.value.paywall.price);

// Wie elders al betaalde (bijvoorbeeld op de website) en terugkomt in de app, hoeft niets te doen.
function onVisible() {
  if (!document.hidden) void recheck();
}
onMounted(() => {
  if (billing.native) billing.loadOffer().catch(() => undefined);
  document.addEventListener('visibilitychange', onVisible);
});
onBeforeUnmount(() => document.removeEventListener('visibilitychange', onVisible));

async function buy() {
  error.value = null;
  try {
    if (!billing.native) return await billing.checkout();
    if (await billing.purchase()) emit('subscribed');
  } catch {
    error.value = t.value.paywall.unavailable;
  }
}

async function restore() {
  error.value = null;
  try {
    if (await billing.restore()) emit('subscribed');
  } catch {
    error.value = t.value.paywall.unavailable;
  }
}

async function recheck() {
  checking.value = true;
  try {
    if (await billing.syncWithServer()) emit('subscribed');
  } finally {
    checking.value = false;
  }
}
</script>

<style scoped>
.paywall { position: absolute; inset: 0; z-index: 50; display: flex; flex-direction: column; overflow-y: auto; padding: calc(var(--safe-top) + 40px) 20px calc(var(--safe-bottom) + 20px); color: #fff; background: var(--vb-ink); }
.paywall__top { display: flex; flex-direction: column; align-items: center; margin-bottom: 28px; text-align: center; }
.paywall__top h1 { margin: 18px 0 4px; font-size: 30px; font-weight: 680; letter-spacing: -.04em; }
.paywall__slogan { margin: 0; color: #9aa1ae; font-size: 17px; }
.paywall__card { padding: 24px 20px 16px; border-radius: 28px; color: var(--vb-ink); background: #fff; }
.paywall__price { margin: 0 0 18px; text-align: center; }
.paywall__price b { font-size: 44px; font-weight: 720; letter-spacing: -.05em; }
.paywall__price span { color: var(--vb-muted); font-size: 16px; }
.paywall__card ul { margin: 0 0 22px; padding: 0; list-style: none; }
.paywall__card li { display: flex; align-items: center; gap: 12px; padding: 9px 2px; font-size: 16px; }
.paywall__card li svg { color: var(--vb-brand); }
.paywall__fine { margin: 10px 0 0; color: var(--vb-subtle); font-size: 12px; line-height: 1.4; text-align: center; }
.paywall__links { margin: 8px 0 0; color: var(--vb-subtle); font-size: 12px; text-align: center; }
.paywall__links a { color: inherit; text-decoration: underline; }
.paywall__logout { margin: 20px auto 0; border: 0; background: none; color: #9aa1ae !important; font-size: 14px; }
</style>
