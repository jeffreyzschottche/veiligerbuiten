<template>
  <!-- Het merkteken: jij (de blauwe stip) met je straal eromheen; de opening is het seintje dat binnenkomt. -->
  <svg class="vb-mark" viewBox="0 0 32 32" :width="size" :height="size" aria-hidden="true">
    <rect width="32" height="32" rx="9" :fill="inverse ? '#fff' : 'var(--vb-ink, #0b0d12)'" />
    <path d="M25.5 16A9.5 9.5 0 1 1 19.25 7.07" fill="none" :stroke="inverse ? '#0b0d12' : '#fff'" stroke-width="2.6" stroke-linecap="round" />
    <circle cx="16" cy="16" r="4.2" fill="#2f6bff" />
  </svg>
</template>

<script setup lang="ts">
withDefaults(defineProps<{ size?: number; inverse?: boolean }>(), { size: 28, inverse: false });
</script>

<style scoped>
.vb-mark { display: block; flex: 0 0 auto; }
</style>
