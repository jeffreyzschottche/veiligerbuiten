<template>
  <BottomSheet :open="open" :title="t.sources.request" @close="emit('close')">
    <template v-if="sent">
      <div class="sent">
        <span class="sent__icon"><Icon name="check" :size="26" :stroke="2.6" /></span>
        <p>{{ t.sources.requestSent }}</p>
      </div>
    </template>
    <template v-else>
      <p class="intro">{{ t.sources.requestIntro }}</p>
      <label class="field">
        <span class="field__label">{{ t.sources.requestName }}</span>
        <input v-model="name" class="input" maxlength="120" :placeholder="t.sources.requestNamePlaceholder">
      </label>
      <label class="field">
        <span class="field__label">{{ t.sources.requestUrl }}</span>
        <input v-model="url" class="input" type="url" inputmode="url" maxlength="300" placeholder="https://">
      </label>
      <label class="field">
        <span class="field__label">{{ t.sources.requestNote }}</span>
        <textarea v-model="note" class="input" maxlength="1000" :placeholder="t.sources.requestNotePlaceholder" />
      </label>
      <p v-if="error" class="error-text">{{ error }}</p>
    </template>

    <template #footer>
      <button v-if="sent" class="btn btn--soft btn--block" @click="emit('close')">{{ t.common.close }}</button>
      <button v-else class="btn btn--ink btn--block" :disabled="sending || !name.trim()" @click="send">{{ t.sources.requestSend }}</button>
    </template>
  </BottomSheet>
</template>

<script setup lang="ts">
const props = defineProps<{ open: boolean }>();
const emit = defineEmits<{ close: [] }>();
const { t } = useAppCopy();

const name = ref('');
const url = ref('');
const note = ref('');
const sending = ref(false);
const sent = ref(false);
const error = ref<string | null>(null);

watch(() => props.open, (open) => {
  if (!open) return;
  // Na een verstuurde aanvraag begin je weer leeg; een half ingevulde blijft staan.
  if (sent.value) name.value = url.value = note.value = '';
  sent.value = false;
  error.value = null;
});

async function send() {
  sending.value = true;
  error.value = null;
  try {
    const link = url.value.trim();
    await useApi().post('/source-requests', {
      name: name.value.trim(),
      url: link ? (/^https?:\/\//i.test(link) ? link : `https://${link}`) : null,
      note: note.value.trim() || null,
    });
    sent.value = true;
  } catch (caught: any) {
    error.value = caught?.data?.errors ? Object.values(caught.data.errors).flat()[0] as string : t.value.errors.generic;
  } finally {
    sending.value = false;
  }
}
</script>

<style scoped>
.intro { margin: 0 4px 16px; color: var(--vb-muted); font-size: 15px; line-height: 1.45; }
.sent { padding: 24px 8px 12px; text-align: center; }
.sent__icon { display: inline-grid; place-items: center; width: 56px; height: 56px; margin-bottom: 12px; border-radius: 18px; color: var(--vb-ok); background: #e6f6ec; }
.sent p { margin: 0; font-size: 16px; line-height: 1.45; }
</style>
