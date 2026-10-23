<template>
  <!-- Gestileerde binnenstad: het IJ, het station en de grachtengordel. Alleen decor voor de mockups. -->
  <svg class="mini-map" :class="{ 'mini-map--dark': dark }" viewBox="0 0 300 650" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
    <rect width="300" height="650" class="mm-land" />
    <g class="mm-blocks">
      <rect v-for="b in blocks" :key="b.join()" :x="b[0]" :y="b[1]" :width="b[2]" :height="b[3]" rx="3" />
    </g>
    <path class="mm-park" d="M-20 520c40-18 92-10 120 14s30 70-10 96-100 30-130 10Z" />
    <path class="mm-park" d="M232 420c22-6 52 4 60 24s-6 42-30 44-44-10-48-30 2-34 18-38Z" />
    <path class="mm-water" d="M-20 70C60 92 140 104 320 82v78C200 178 90 172-20 150Z" />
    <rect class="mm-station" x="104" y="160" width="92" height="24" rx="5" />
    <g class="mm-canal">
      <path d="M40 196a110 110 0 0 0 220 0" />
      <path d="M10 196a140 140 0 0 0 280 0" />
      <path d="M-20 196a170 170 0 0 0 340 0" />
      <path d="M-50 196a200 200 0 0 0 400 0" />
    </g>
    <g class="mm-road">
      <path d="M150 184V650" />
      <path d="M150 330 40 650" />
      <path d="M150 330 270 650" />
      <path d="M-20 420c90 30 220 30 340-10" />
      <path d="M-10 192h320" />
    </g>
    <g class="mm-street">
      <path d="M95 200 70 640" /><path d="M205 200l25 440" /><path d="M0 300h300" /><path d="M0 520h300" /><path d="M0 600h300" />
      <path d="M100 192 10 290" /><path d="M200 192l90 100" /><path d="M0 470c100-14 200-14 300 0" />
    </g>
    <g class="mm-label">
      <text x="150" y="128" text-anchor="middle">IJ</text>
      <text x="150" y="232" text-anchor="middle">Damrak</text>
      <text x="58" y="560">Vondelpark</text>
    </g>
  </svg>
</template>

<script setup lang="ts">
withDefaults(defineProps<{ dark?: boolean }>(), { dark: false });

// Vaste bouwblokken voor wat stadstextuur; deterministisch zodat SSR en client gelijk zijn.
const blocks = Array.from({ length: 70 }, (_, i) => {
  const col = i % 7;
  const row = Math.floor(i / 7);
  const jitter = ((i * 37) % 11) - 5;
  return [col * 44 + 6 + jitter, 204 + row * 46 + ((i * 13) % 7), 30 + ((i * 7) % 9), 30 + ((i * 11) % 8)];
});
</script>

<style scoped>
.mini-map { position: absolute; inset: 0; width: 100%; height: 100%; display: block; }
.mm-land { fill: #eceef2; }
.mm-park { fill: #e2e7e3; }
.mm-water { fill: #d5def3; }
.mm-blocks rect { fill: #e4e7ec; }
.mm-station { fill: #d9dde4; }
.mm-label text { font: 600 9px var(--vb-font); fill: #8b919d; letter-spacing: .02em; }
.mm-canal path { fill: none; stroke: #d5def3; stroke-width: 7; }
.mm-road path { fill: none; stroke: #fff; stroke-width: 9; stroke-linecap: round; }
.mm-street path { fill: none; stroke: #fff; stroke-width: 4; stroke-linecap: round; opacity: .85; }
.mini-map--dark .mm-land { fill: #13161d; }
.mini-map--dark .mm-park { fill: #172019; }
.mini-map--dark .mm-water { fill: #172440; }
.mini-map--dark .mm-blocks rect { fill: #181b22; }
.mini-map--dark .mm-station { fill: #222630; }
.mini-map--dark .mm-label text { fill: #5a606d; }
.mini-map--dark .mm-canal path { stroke: #172440; }
.mini-map--dark .mm-road path { stroke: #262b36; }
.mini-map--dark .mm-street path { stroke: #1f232c; }
</style>
