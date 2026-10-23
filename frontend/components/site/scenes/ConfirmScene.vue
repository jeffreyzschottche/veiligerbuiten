<template>
  <div class="scene" :class="{ 'is-playing': playing }">
    <MiniMap />
    <div class="focus">
      <span class="reach" />
      <span class="dot" />
      <span class="plus plus--1">+1</span>
      <span class="plus plus--2">+1</span>
      <span class="plus plus--3">+1</span>
    </div>
    <div class="card">
      <span class="tone" />
      <div class="counts">
        <b class="n n--1">1×</b><b class="n n--2">4×</b><b class="n n--3">12×</b>
        <small>{{ c.label }}</small>
      </div>
      <div class="reach-label">
        <small>{{ c.reach }}</small>
        <b class="n n--1">300 m</b><b class="n n--2">1 km</b><b class="n n--3">2 km</b>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { SiteCopy } from '~/locales/site';

defineProps<{ c: SiteCopy['scene']['confirm'] }>();
const playing = useScenePlaying();
</script>

<style scoped>
.scene { position: absolute; inset: 0; --loop: 8s; }
.focus { position: absolute; left: 50%; top: 42%; }
.reach { position: absolute; left: 0; top: 0; width: 6em; height: 6em; transform: translate(-50%, -50%); border-radius: 50%; border: 1.5px dashed var(--vb-danger); background: #e5484d12; }
.dot { position: absolute; left: 0; top: 0; width: 1em; height: 1em; transform: translate(-50%, -50%); border: 2.5px solid #fff; border-radius: 50%; background: var(--vb-danger); box-shadow: 0 3px 10px #e5484d66; }
.plus { position: absolute; left: 0; top: 0; padding: .15em .5em; border-radius: 99px; background: var(--vb-ink); color: #fff; font-size: .72em; font-weight: 650; opacity: 0; }
.plus--1 { --dx: 2.2em; }
.plus--2 { --dx: -4.2em; }
.plus--3 { --dx: 1.4em; }

.card { position: absolute; left: 5.5%; right: 5.5%; bottom: 6%; display: flex; align-items: center; gap: .9em; padding: 1em 1.1em; border-radius: 1.3em; background: #fff; box-shadow: var(--vb-shadow); }
.tone { width: .8em; height: .8em; border-radius: 50%; background: var(--vb-danger); }
.counts, .reach-label { position: relative; display: grid; }
.reach-label { margin-left: auto; text-align: right; }
.card small { font-size: .7em; color: var(--vb-muted); }
.n { grid-area: 1 / 1; font-size: 1.35em; font-weight: 700; letter-spacing: -.03em; font-variant-numeric: tabular-nums; opacity: 0; }
.reach-label .n { grid-area: 2 / 1; }
/* Eindstand zonder animatie: 12× bevestigd, groot bereik. */
.n--3 { opacity: 1; }
.dot { width: 2em; height: 2em; }
.reach { width: 15em; height: 15em; }

.is-playing .reach { animation: reach var(--loop) var(--vb-ease) infinite; }
.is-playing .dot { animation: grow var(--loop) var(--vb-ease) infinite; }
.is-playing .plus--1 { animation: plus var(--loop) infinite; animation-delay: calc(var(--loop) * .2); }
.is-playing .plus--2 { animation: plus var(--loop) infinite; animation-delay: calc(var(--loop) * .28); }
.is-playing .plus--3 { animation: plus var(--loop) infinite; animation-delay: calc(var(--loop) * .55); }
.is-playing .n--1 { animation: n1 var(--loop) infinite; }
.is-playing .n--2 { animation: n2 var(--loop) infinite; }
.is-playing .n--3 { animation: n3 var(--loop) infinite; }

@keyframes grow { 0%, 20% { width: 1em; height: 1em; } 30%, 52% { width: 1.45em; height: 1.45em; } 62%, 92% { width: 2em; height: 2em; } 100% { width: 1em; height: 1em; } }
@keyframes reach { 0%, 20% { width: 6em; height: 6em; } 30%, 52% { width: 10em; height: 10em; } 62%, 92% { width: 15em; height: 15em; } 100% { width: 6em; height: 6em; } }
@keyframes plus { 0% { opacity: 0; transform: translate(var(--dx), 0); } 4% { opacity: 1; } 14% { opacity: 0; transform: translate(var(--dx), -3em); } 100% { opacity: 0; } }
@keyframes n1 { 0%, 24% { opacity: 1; } 28%, 100% { opacity: 0; } }
@keyframes n2 { 0%, 26% { opacity: 0; } 30%, 54% { opacity: 1; } 58%, 100% { opacity: 0; } }
@keyframes n3 { 0%, 56% { opacity: 0; } 60%, 94% { opacity: 1; } 98%, 100% { opacity: 0; } }

</style>
