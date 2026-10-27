<script setup lang="ts">
import { Capacitor } from '@capacitor/core';

// Oude registratielinks blijven bruikbaar; de mobiele app heeft alleen een login.
const mobile = useRuntimeConfig().public.mobileBuild || Capacitor.isNativePlatform();
await navigateTo(mobile ? '/app/start' : '/aanmelden', { replace: true });
</script>

<template>
  <div />
</template>
