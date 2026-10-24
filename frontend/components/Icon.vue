<template>
  <svg class="vb-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" :stroke-width="stroke" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <path v-for="(path, index) in iconPaths[name]" :key="index" :d="path" />
  </svg>
</template>

<script setup lang="ts">
import { iconPaths, type IconName } from '~/utils/icons';

const props = withDefaults(defineProps<{ name: IconName; size?: number; stroke?: number }>(), { size: 20, stroke: 2 });
</script>

<style scoped>
.vb-icon { display: block; width: v-bind('`${props.size}px`'); height: v-bind('`${props.size}px`'); flex: 0 0 auto; }
</style>
