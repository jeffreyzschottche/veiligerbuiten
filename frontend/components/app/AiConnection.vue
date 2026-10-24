<template>
  <div>
    <p class="group__label">{{ t.account.ai }}</p>
    <div class="card ai">
      <div class="ai__intro">
        <span class="row__icon ai__icon"><Icon name="sparkles" :size="18" /></span>
        <p>{{ t.account.aiIntro }}</p>
      </div>

      <template v-if="data?.mcp_url">
        <p class="ai__label">{{ t.account.aiUrl }}</p>
        <button class="ai__secret" @click="copy(data.mcp_url)">
          <code>{{ data.mcp_url }}</code>
          <span>{{ copied === data.mcp_url ? t.account.copied : t.account.copy }}</span>
        </button>
        <p class="group__help">{{ t.account.aiUrlHelp }}</p>
        <div class="ai__buttons">
          <button class="btn btn--soft btn--sm" :disabled="busy" @click="rotate">{{ t.account.aiRotate }}</button>
          <button class="btn btn--ghost btn--sm" :disabled="busy" @click="disconnect">{{ t.account.aiDisconnect }}</button>
        </div>
      </template>
      <button v-else class="btn btn--ink btn--block" :disabled="busy || !loaded" @click="rotate">{{ t.account.aiConnect }}</button>

      <details class="ai__webhook" :open="!!data?.webhook_url">
        <summary>{{ t.account.webhook }}</summary>
        <p class="group__help">{{ t.account.webhookHelp }}</p>
        <div class="ai__hook">
          <input v-model="webhook" class="input" type="url" inputmode="url" placeholder="https://" autocapitalize="off" autocorrect="off">
          <button class="btn btn--soft btn--sm" :disabled="busy || webhook === (data?.webhook_url ?? '')" @click="saveWebhook">{{ t.common.save }}</button>
        </div>
        <template v-if="data?.webhook_secret">
          <p class="ai__label">{{ t.account.webhookSecret }}</p>
          <button class="ai__secret" @click="copy(data.webhook_secret)">
            <code>{{ data.webhook_secret }}</code>
            <span>{{ copied === data.webhook_secret ? t.account.copied : t.account.copy }}</span>
          </button>
          <button class="btn btn--soft btn--sm" :disabled="busy" @click="test">{{ tested ? t.account.webhookSent : t.account.webhookTest }}</button>
        </template>
      </details>
      <p v-if="error" class="error-text">{{ error }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Clipboard } from '@capacitor/clipboard';

interface AiConnectionData { mcp_url: string | null; mcp_endpoint: string; token: string | null; webhook_url: string | null; webhook_secret: string | null }

const { t } = useAppCopy();
const data = ref<AiConnectionData | null>(null);
const loaded = ref(false);
const busy = ref(false);
const webhook = ref('');
const copied = ref<string | null>(null);
const tested = ref(false);
const error = ref<string | null>(null);

onMounted(async () => {
  try {
    apply((await useApi().get<{ data: AiConnectionData }>('/me/ai')).data);
  } finally {
    loaded.value = true;
  }
});

function apply(next: AiConnectionData) {
  data.value = next;
  webhook.value = next.webhook_url ?? '';
}

async function run(action: () => Promise<{ data: AiConnectionData }>) {
  busy.value = true;
  error.value = null;
  try {
    apply((await action()).data);
  } catch (caught: any) {
    error.value = caught?.data?.errors ? Object.values(caught.data.errors).flat()[0] as string : caught?.data?.message ?? t.value.errors.generic;
  } finally {
    busy.value = false;
  }
}

const rotate = () => run(() => useApi().post('/me/ai/token'));
const disconnect = () => run(() => useApi().delete('/me/ai/token'));
const saveWebhook = () => run(() => useApi().put('/me/ai/webhook', { webhook_url: webhook.value.trim() || null }));

async function test() {
  busy.value = true;
  error.value = null;
  try {
    await useApi().post('/me/ai/webhook/test');
    tested.value = true;
    setTimeout(() => { tested.value = false; }, 3000);
  } catch (caught: any) {
    error.value = caught?.data?.message ?? t.value.errors.generic;
  } finally {
    busy.value = false;
  }
}

async function copy(value: string) {
  await Clipboard.write({ string: value }).catch(() => navigator.clipboard?.writeText(value));
  copied.value = value;
  setTimeout(() => { copied.value = null; }, 2000);
}
</script>

<style scoped>
.ai { padding: 16px; }
.ai__intro { display: flex; gap: 12px; margin-bottom: 14px; }
.ai__intro p { margin: 0; color: var(--vb-ink-3); font-size: 14px; line-height: 1.45; }
.ai__icon { color: var(--vb-brand); background: var(--vb-brand-50); }
.ai__label { margin: 4px 0 6px; color: var(--vb-muted); font-size: 13px; font-weight: 560; }
.ai__secret { display: flex; align-items: center; gap: 10px; width: 100%; padding: 12px 14px; border: 0; border-radius: 12px; background: var(--vb-paper); text-align: left; }
.ai__secret code { flex: 1; overflow: hidden; font-family: var(--vb-mono); font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }
.ai__secret span { color: var(--vb-brand); font-size: 13px; font-weight: 600; }
.ai__buttons { display: flex; gap: 8px; margin-top: 12px; }
.ai__webhook { margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--vb-line-2); }
.ai__webhook summary { cursor: pointer; font-size: 15px; font-weight: 580; list-style: none; }
.ai__webhook summary::-webkit-details-marker { display: none; }
.ai__webhook summary::after { content: '+'; float: right; color: var(--vb-subtle); }
.ai__webhook[open] summary::after { content: '–'; }
.ai__hook { display: flex; gap: 8px; margin: 10px 0; }
.ai__hook .input { min-height: 44px; font-size: 14px; }
</style>
