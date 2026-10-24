<template>
  <nav class="tabbar" :aria-label="t.tabs.map">
    <button v-for="tab in tabs" :key="tab.id" class="tabbar__tab" :aria-current="modelValue === tab.id ? 'page' : undefined" @click="emit('update:modelValue', tab.id)">
      <Icon :name="tab.icon" :size="23" :stroke="modelValue === tab.id ? 2.3 : 1.8" />
      <span>{{ t.tabs[tab.id] }}</span>
    </button>
  </nav>
</template>

<script setup lang="ts">
import type { IconName } from '~/utils/icons';

import type { Tab } from '~/types/App';
defineProps<{ modelValue: Tab }>();
const emit = defineEmits<{ 'update:modelValue': [tab: Tab] }>();
const { t } = useAppCopy();
const tabs: Array<{ id: Tab; icon: IconName }> = [
  { id: 'map', icon: 'map' },
  { id: 'places', icon: 'pin' },
  { id: 'account', icon: 'user' },
];
</script>

<style scoped>
.tabbar { position: absolute; z-index: 30; right: 0; bottom: 0; left: 0; display: flex; height: var(--tabbar-h); padding: 6px 12px var(--safe-bottom); background: #ffffffeb; box-shadow: 0 -1px 0 var(--vb-line); backdrop-filter: saturate(1.6) blur(18px); -webkit-backdrop-filter: saturate(1.6) blur(18px); }
.tabbar__tab { display: flex; flex: 1; flex-direction: column; align-items: center; justify-content: center; gap: 3px; border: 0; background: none; color: var(--vb-subtle) !important; font-size: 11px; font-weight: 560; }
.tabbar__tab[aria-current='page'] { color: var(--vb-ink) !important; }
</style>
