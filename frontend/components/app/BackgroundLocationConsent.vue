<template>
  <div v-if="available" class="group">
    <div class="card">
      <div class="row">
        <span class="row__icon"><Icon name="locate" :size="18" /></span>
        <span class="row__main"><span class="row__title">{{ t.backgroundLocation.title }}</span><span class="row__sub">{{ granted ? t.backgroundLocation.enabled : t.backgroundLocation.summary }}</span></span>
        <button class="btn btn--sm" :class="granted ? 'btn--soft' : 'btn--brand'" :disabled="granted" @click="open = true">{{ granted ? t.start.allowed : t.backgroundLocation.setup }}</button>
      </div>
    </div>
    <Teleport to=".vb-app" defer>
    <BottomSheet :open="open" :title="t.backgroundLocation.title" @close="open = false">
      <p class="disclosure">{{ t.backgroundLocation.disclosure }}</p>
      <p class="disclosure">{{ t.backgroundLocation.data }}</p>
      <p class="instructions">{{ fill(t.backgroundLocation.instructions, { option: optionLabel || t.backgroundLocation.option }) }}</p>
      <p v-if="error" class="error-text">{{ error }}</p>
      <button class="btn btn--ink btn--block" :disabled="busy" @click="allow">{{ busy ? '…' : t.backgroundLocation.continue }}</button>
      <button class="btn btn--ghost btn--block" @click="open = false">{{ t.start.skip }}</button>
    </BottomSheet>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { Capacitor, registerPlugin } from '@capacitor/core';
import { App } from '@capacitor/app';
import type { PluginListenerHandle } from '@capacitor/core';

interface BackgroundLocationStatus { granted: boolean; optionLabel?: string }
const background = registerPlugin<{ check(): Promise<BackgroundLocationStatus>; request(): Promise<BackgroundLocationStatus> }>('BackgroundLocation');
const available = Capacitor.getPlatform() === 'android';
const { t, fill } = useAppCopy();
const device = useDevice();
const open = ref(false);
const granted = ref(false);
const optionLabel = ref('');
const busy = ref(false);
const error = ref('');
let listener: PluginListenerHandle | undefined;

async function refresh() {
  if (!available) return;
  const result = await background.check().catch(() => null);
  granted.value = result?.granted ?? false;
  optionLabel.value = result?.optionLabel ?? '';
  if (granted.value) open.value = false;
}

onMounted(async () => {
  if (!available) return;
  await refresh();
  listener = await App.addListener('appStateChange', ({ isActive }) => { if (isActive) void refresh(); });
});
onBeforeUnmount(() => { void listener?.remove(); });

async function allow() {
  error.value = '';
  busy.value = true;
  try {
    if ((await device.locationPermission()) !== 'granted' && !await device.enableLocation()) {
      error.value = t.value.backgroundLocation.foregroundRequired;
      return;
    }
    await background.request();
    await refresh();
  } catch {
    error.value = t.value.errors.generic;
  } finally {
    busy.value = false;
  }
}
</script>

<style scoped>
.disclosure { margin: 0 0 16px; font-size: 16px; line-height: 1.5; }
.instructions { margin: 0 0 20px; color: var(--vb-muted); line-height: 1.5; }
</style>
