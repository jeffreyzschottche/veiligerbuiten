<template>
  <div ref="root" class="phone" :class="{ 'phone--dark': dark }">
    <div class="phone__screen">
      <slot />
      <div class="phone__status" aria-hidden="true">
        <span>{{ time }}</span>
        <span class="phone__island" />
        <span class="phone__icons">
          <svg viewBox="0 0 18 12" width="17"><rect x="0" y="8" width="3" height="4" rx="1" /><rect x="5" y="5.5" width="3" height="6.5" rx="1" /><rect x="10" y="3" width="3" height="9" rx="1" /><rect x="15" y="0" width="3" height="12" rx="1" /></svg>
          <svg viewBox="0 0 16 12" width="15"><path d="M8 2.2c2.4 0 4.6.9 6.2 2.5l1.2-1.2A10.4 10.4 0 0 0 8 .5C5.2.5 2.6 1.6.6 3.5l1.2 1.2A8.7 8.7 0 0 1 8 2.2Zm0 3.4c1.5 0 2.8.6 3.8 1.5l1.2-1.2A7 7 0 0 0 8 3.9 7 7 0 0 0 3 5.9l1.2 1.2c1-.9 2.3-1.5 3.8-1.5Zm0 3.3c.6 0 1.1.2 1.5.6L8 11 6.5 9.5c.4-.4.9-.6 1.5-.6Z" /></svg>
          <svg viewBox="0 0 27 12" width="25"><rect x=".5" y=".5" width="22" height="11" rx="3.5" fill="none" stroke="currentColor" opacity=".4" /><rect x="2" y="2" width="17" height="8" rx="2" /><rect x="24" y="4" width="2" height="4" rx="1" opacity=".4" /></svg>
        </span>
      </div>
      <div class="phone__home" aria-hidden="true" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { playingKey } from '~/composables/usePlayInView';

withDefaults(defineProps<{ dark?: boolean; time?: string }>(), { dark: false, time: '9:41' });
const root = ref<HTMLElement | null>(null);
provide(playingKey, usePlayInView(root));
</script>

<style scoped>
.phone { --w: var(--phone-w, 300px); position: relative; width: var(--w); aspect-ratio: 9 / 19.5; padding: calc(var(--w) * .035); border-radius: calc(var(--w) * .165); background: linear-gradient(145deg, #2b2f38, #0b0d12 40%, #1b1e25); box-shadow: inset 0 0 0 1.5px #3a3f4a, var(--vb-shadow-lg); flex: 0 0 auto; }
.phone__screen { position: relative; width: 100%; height: 100%; overflow: hidden; border-radius: calc(var(--w) * .135); background: var(--vb-paper); color: var(--vb-ink); isolation: isolate; font-size: calc(var(--w) * .043); container-type: inline-size; }
.phone__status { position: absolute; z-index: 50; top: 0; left: 0; right: 0; display: flex; align-items: center; justify-content: space-between; height: 11%; max-height: 54px; padding: 0 8.5% 0 11%; font-size: 1.02em; font-weight: 620; letter-spacing: -.01em; pointer-events: none; }
.phone__island { position: absolute; top: 28%; left: 50%; width: 32%; height: 38%; transform: translateX(-50%); border-radius: 99px; background: #000; }
.phone__icons { display: flex; align-items: center; gap: 5px; }
.phone__icons svg { fill: currentColor; height: auto; }
.phone__home { position: absolute; z-index: 50; bottom: 1.1%; left: 50%; width: 36%; height: 4px; transform: translateX(-50%); border-radius: 99px; background: var(--vb-ink); opacity: .85; }
.phone--dark .phone__screen { background: var(--vb-ink); color: #fff; }
.phone--dark .phone__home { background: #fff; }
</style>
