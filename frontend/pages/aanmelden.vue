<template>
  <WebAccount :mode="route.query.mode === 'login' ? 'login' : 'signup'" />
</template>

<script setup lang="ts">
definePageMeta({ layout: false });
const route = useRoute();
</script>
