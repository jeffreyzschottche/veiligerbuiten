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

      <template v-if="billing.available">
        <p v-if="error" class="error-text">{{ error }}</p>
        <button class="btn btn--brand btn--block" :disabled="billing.busy.value" @click="buy">{{ billing.busy.value ? t.paywall.checking : t.paywall.buy }}</button>
        <button class="btn btn--ghost btn--block" :disabled="billing.busy.value" @click="restore">{{ t.paywall.restore }}</button>
      </template>
      <template v-else>
        <p class="paywall__web">{{ t.paywall.web }}</p>
        <StoreButtons :copy="siteCopy[locale]" class="paywall__stores" />
        <button class="btn btn--ghost btn--block" @click="recheck">{{ t.common.retry }}</button>
      </template>
      <p class="paywall__fine">{{ t.paywall.fine }}</p>
    </div>
    <button class="paywall__logout" @click="emit('logout')">{{ t.account.logout }}</button>
  </section>
</template>

<script setup lang="ts">
import { siteCopy } from '~/locales/site';

const emit = defineEmits<{ subscribed: []; logout: [] }>();
const { t, locale } = useAppCopy();
const billing = useBilling();
const error = ref<string | null>(null);
const price = computed(() => billing.monthly.value?.product.priceString ?? t.value.paywall.price);

onMounted(() => billing.loadOffer().catch(() => undefined));

async function buy() {
  error.value = null;
  try {
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
  await useAuthStore().refreshAccount().catch(() => undefined);
  if (useAuthStore().isSubscribed) emit('subscribed');
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
.paywall__web { margin: 0 0 14px; color: var(--vb-muted); font-size: 14px; line-height: 1.45; text-align: center; }
.paywall__stores { justify-content: center; margin-bottom: 6px; }
.paywall__fine { margin: 10px 0 0; color: var(--vb-subtle); font-size: 12px; line-height: 1.4; text-align: center; }
.paywall__logout { margin: 20px auto 0; border: 0; background: none; color: #9aa1ae !important; font-size: 14px; }
</style>
