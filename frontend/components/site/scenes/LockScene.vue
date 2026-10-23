<template>
  <div class="scene" :class="{ 'is-playing': playing }">
    <div class="clock">
      <small>{{ l.date }}</small>
      <b>{{ l.time }}</b>
    </div>
    <div class="stack">
      <article class="note note--danger n3">
        <LogoMark :size="28" inverse />
        <div><p><b>VEILIGBUITEN</b><span>{{ now }}</span></p><strong>{{ l.n3 }}</strong><small>{{ l.n3b }}</small></div>
      </article>
      <article class="note n2">
        <LogoMark :size="28" inverse />
        <div><p><b>VEILIGBUITEN</b><span>4 min</span></p><strong>{{ l.n2 }}</strong><small>{{ l.n2b }}</small></div>
      </article>
      <article class="note n1">
        <LogoMark :size="28" inverse />
        <div><p><b>VEILIGBUITEN</b><span>12 min</span></p><strong>{{ l.n1 }}</strong><small>{{ l.n1b }}</small></div>
      </article>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { SiteCopy } from '~/locales/site';

defineProps<{ l: SiteCopy['scene']['lock']; now: string }>();
const playing = useScenePlaying();
</script>

<style scoped>
.scene { position: absolute; inset: 0; --loop: 9s; background: radial-gradient(120% 70% at 50% 0%, #1d3a8a 0%, #0f1a3a 45%, #0b0d12 100%); color: #fff; }
.clock { display: grid; justify-items: center; padding-top: 18%; }
.clock small { font-size: .9em; font-weight: 520; opacity: .85; }
.clock b { font-size: 4.6em; font-weight: 600; letter-spacing: -.045em; line-height: 1; }
.stack { position: absolute; left: 4%; right: 4%; bottom: 8%; display: flex; flex-direction: column; gap: .5em; }
.note { position: relative; display: flex; gap: .7em; padding: .8em .9em; border-radius: 1.3em; background: #ffffff2b; backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px); }
.note div { min-width: 0; flex: 1; }
.note p { display: flex; justify-content: space-between; margin: 0 0 .1em; font-size: .62em; letter-spacing: .04em; opacity: .75; }
.note strong { display: block; font-size: .82em; font-weight: 640; line-height: 1.25; }
.note small { display: block; font-size: .72em; opacity: .8; }
.note :deep(svg) { border-radius: 7px; }
.note--danger { background: #ffffffee; color: var(--vb-ink); box-shadow: 0 0 0 2px var(--vb-danger), 0 12px 30px #e5484d55; }
.note--danger strong { color: var(--vb-danger); }
.note--danger :deep(rect) { fill: var(--vb-ink); }
.note--danger :deep(path) { stroke: #fff; }
/* Gebundeld bericht: twee kaartjes erachter. */
.n2 { margin-bottom: .7em; box-shadow: 0 .4em 0 -.15em #ffffff1f, 0 .75em 0 -.35em #ffffff12; }

.is-playing .n1 { animation: in1 var(--loop) var(--vb-ease) infinite; }
.is-playing .n2 { animation: in2 var(--loop) var(--vb-ease) infinite; }
.is-playing .n3 { animation: in3 var(--loop) var(--vb-ease) infinite; }

@keyframes in1 { 0%, 6% { opacity: 0; transform: translateY(1.5em); } 12%, 92% { opacity: 1; transform: none; } 100% { opacity: 0; } }
@keyframes in2 { 0%, 26% { opacity: 0; transform: translateY(1.5em); } 32%, 92% { opacity: 1; transform: none; } 100% { opacity: 0; } }
@keyframes in3 { 0%, 48% { opacity: 0; transform: translateY(1.5em) scale(.96); } 54% { opacity: 1; transform: scale(1.03); } 58%, 92% { opacity: 1; transform: none; } 100% { opacity: 0; } }

</style>
