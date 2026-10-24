<template>
  <span class="vb-logo" :class="{ 'vb-logo--inverse': inverse }">
    <LogoMark :size="size" :inverse="inverse" />
    <span v-if="wordmark" class="vb-logo__word" :style="{ fontSize: `${Math.round(size * 0.66)}px` }">veiligerbuiten</span>
  </span>
</template>

<script setup lang="ts">
withDefaults(defineProps<{ size?: number; wordmark?: boolean; inverse?: boolean }>(), { size: 28, wordmark: true, inverse: false });
</script>

<style scoped>
.vb-logo { display: inline-flex; align-items: center; gap: .45em; color: var(--vb-ink); line-height: 1; }
.vb-logo--inverse { color: #fff; }
.vb-logo__word { font-weight: 640; letter-spacing: -.035em; }
</style>
