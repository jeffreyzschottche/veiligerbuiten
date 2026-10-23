<template>
  <svg class="vb-icon" :class="`vb-icon--${name}`" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <path v-for="(path, index) in paths" :key="index" :d="path" />
  </svg>
</template>

<script setup lang="ts">
type IconName = 'map' | 'info' | 'locate' | 'filter' | 'refresh' | 'plus' | 'bell' | 'settings' | 'close' | 'chevron' | 'external' | 'share' | 'check' | 'star' | 'fire' | 'shield-alert' | 'car' | 'construction' | 'cloud-rain' | 'flask' | 'users' | 'paw' | 'circle-help' | 'alert-triangle';
const props = withDefaults(defineProps<{ name: IconName; size?: number }>(), { size: 20 });
const iconPaths: Record<IconName, string[]> = {
  map: ['M14.5 4 20 7l-5.5 3L9 7l5.5-3Z', 'M4 7l5 3v10l-5-3V7Z', 'm9 10 5.5 3v7L9 17v-7Z', 'm20 7-5.5 3v10L20 17V7Z'],
  info: ['M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z', 'M12 16v-4', 'M12 8h.01'],
  locate: ['M12 2v3', 'M12 19v3', 'M2 12h3', 'M19 12h3', 'M12 17a5 5 0 1 0 0-10 5 5 0 0 0 0 10Z', 'M12 14a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z'],
  filter: ['M4 6h16', 'M7 12h10', 'M10 18h4'], refresh: ['M20 11a8.1 8.1 0 0 0-15.5-2M4 4v5h5', 'M4 13a8.1 8.1 0 0 0 15.5 2M20 20v-5h-5'],
  plus: ['M12 5v14', 'M5 12h14'], bell: ['M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9', 'M10 21h4'],
  settings: ['M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.38a2 2 0 0 0-.73-2.73l-.15-.09a2 2 0 0 1-1-1.74v-.51a2 2 0 0 1 1-1.72l.15-.1a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2Z', 'M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z'],
  close: ['M18 6 6 18', 'm6 6 12 12'], chevron: ['m9 18 6-6-6-6'], external: ['M15 3h6v6', 'M10 14 21 3', 'M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6'], share: ['M4 12v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7', 'm16 6-4-4-4 4', 'M12 2v13'],
  check: ['m5 12 4 4L19 6'], star: ['m12 2 3.1 6.3 6.9 1-5 4.8 1.2 6.8-6.2-3.3-6.2 3.3 1.2-6.8-5-4.8 6.9-1L12 2Z'], fire: ['M12 22c4 0 7-2.7 7-6.4 0-3-1.6-5-4.4-7.8.2 2.6-1 4-2.4 4.8.2-3.5-1.5-5.5-3.6-7.6C7.2 12.2 5 14.6 5 17.1 5 19.8 7.8 22 12 22Z'],
  'shield-alert': ['M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z', 'M12 8v4', 'M12 16h.01'], car: ['M5 17h14', 'M6 17 4 11l2-5h12l2 5-2 6', 'M6 17v2', 'M18 17v2', 'M7.5 12h.01', 'M16.5 12h.01'],
  construction: ['M3 21h18', 'M6 21V10l6-4 6 4v11', 'M9 21v-5h6v5', 'M4 10h16'], 'cloud-rain': ['M17.5 19H9a7 7 0 1 1 6.7-9A5 5 0 1 1 17.5 19Z', 'm8 19-1 2', 'm12 19-1 2', 'm16 19-1 2'],
  flask: ['M9 3h6', 'M10 3v7l-5.4 8.6A2 2 0 0 0 6.3 22h11.4a2 2 0 0 0 1.7-3.4L14 10V3', 'M8 16h8'], users: ['M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2', 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z', 'M22 21v-2a4 4 0 0 0-3-3.87', 'M16 3.13a4 4 0 0 1 0 7.75'],
  paw: ['M11.5 16.5c-1.1 0-2 .9-2 2 0 1.7 1.4 2.5 2.5 2.5s2.5-.8 2.5-2.5c0-1.1-.9-2-2-2h-1Z', 'M6.5 14a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z', 'M17.5 14a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z', 'M9 9a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z', 'M15 9a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z'],
  'circle-help': ['M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z', 'M9.1 9a3 3 0 1 1 5.7 1.4c-.6.8-1.5 1.2-2.2 1.8-.6.5-.6 1-.6 1.8', 'M12 17h.01'], 'alert-triangle': ['M10.3 3.9 2.1 18a2 2 0 0 0 1.7 3h16.4a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z', 'M12 9v4', 'M12 17h.01'],
};
const paths = computed(() => iconPaths[props.name]);
</script>

<style scoped>
.vb-icon { display: block; width: v-bind('`${size}px`'); height: v-bind('`${size}px`'); flex: 0 0 auto; }
</style>
