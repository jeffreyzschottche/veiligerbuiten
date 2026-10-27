<script setup lang="ts">
import { Capacitor } from '@capacitor/core';

const mobile = useRuntimeConfig().public.mobileBuild || Capacitor.isNativePlatform();
await navigateTo(mobile ? { path: '/app/start', query: { mode: 'login' } } : '/account', { replace: true });
</script>

<template>
  <div />
</template>
