<template>
  <div class="scene" :class="{ 'is-playing': playing }">
    <section class="panel panel--cats">
      <h4>{{ r.title }}</h4>
      <div class="grid">
        <span v-for="(cat, index) in r.cats" :key="cat" class="cat" :class="{ 'cat--pick': index === 2 }">
          <i :class="`ic ic--${index}`" />{{ cat }}
        </span>
      </div>
      <span class="tap tap--1" />
    </section>

    <section class="panel panel--sub">
      <p class="back">‹ {{ r.sub }}</p>
      <h4>{{ r.title }}</h4>
      <span v-for="(sub, index) in r.subs" :key="sub" class="row" :class="{ 'row--pick': index === 0 }">{{ sub }}<i /></span>
      <span class="place"><b>●</b> Damrak 1, Amsterdam</span>
      <span class="send">OK</span>
      <span class="tap tap--2" />
    </section>

    <section class="panel panel--done">
      <span class="check"><svg viewBox="0 0 24 24" width="30" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6" /></svg></span>
      <h4>{{ r.done }}</h4>
      <p>{{ r.doneText }}</p>
      <span class="quota"><i class="on" /><i class="on" /><i />{{ r.left }}</span>
    </section>
  </div>
</template>

<script setup lang="ts">
import type { SiteCopy } from '~/locales/site';

defineProps<{ r: SiteCopy['scene']['report'] }>();
const playing = useScenePlaying();
</script>

<style scoped>
.scene { position: absolute; inset: 0; --loop: 9s; background: #fff; }
.panel { position: absolute; inset: 0; padding: 22% 7% 0; background: #fff; }
h4 { margin: 0 0 .9em; font-size: 1.45em; font-weight: 680; letter-spacing: -.035em; }
.grid { display: grid; grid-template-columns: 1fr 1fr; gap: .6em; }
.cat { display: grid; gap: .7em; padding: .9em .8em; border-radius: 1em; background: var(--vb-paper); font-size: .85em; font-weight: 600; }
.ic { display: block; width: 1.9em; height: 1.9em; border-radius: .6em; background: #fff; box-shadow: inset 0 0 0 1.5px var(--vb-line); }
.ic--0 { background: #ffe9d6; box-shadow: none; }
.ic--1 { background: #fde4e5; box-shadow: none; }
.ic--2 { background: #fde4e5; box-shadow: none; }
.ic--3 { background: #e7edff; box-shadow: none; }
.ic--4 { background: #e7edff; box-shadow: none; }
.cat--pick { background: var(--vb-ink); color: #fff; }
.tap { position: absolute; z-index: 3; width: 2.6em; height: 2.6em; border-radius: 50%; background: #0b0d1226; opacity: 0; }
.tap--1 { left: 14%; top: 50%; }
.tap--2 { left: 30%; top: 33%; }

.panel--sub { transform: translateX(100%); }
.back { margin: 0 0 .6em; font-size: .8em; font-weight: 600; color: var(--vb-brand); }
.row { display: flex; align-items: center; justify-content: space-between; padding: .95em .9em; margin-bottom: .45em; border-radius: .9em; background: var(--vb-paper); font-size: .85em; font-weight: 560; }
.row i { width: 1.1em; height: 1.1em; border-radius: 50%; box-shadow: inset 0 0 0 2px var(--vb-line); }
.place { display: block; margin-top: 1.1em; padding: .8em .9em; border-radius: .9em; box-shadow: inset 0 0 0 1.5px var(--vb-line); font-size: .78em; color: var(--vb-muted); }
.place b { color: var(--vb-brand); }
.send { position: absolute; left: 7%; right: 7%; bottom: 7%; display: grid; place-items: center; height: 3.2em; border-radius: 1em; background: var(--vb-ink); color: #fff; font-weight: 650; font-size: .9em; }

.panel--done { display: grid; align-content: center; justify-items: center; text-align: center; padding: 0 12%; opacity: 0; }
.check { display: grid; place-items: center; width: 4.2em; height: 4.2em; margin-bottom: 1em; border-radius: 50%; background: var(--vb-ok); box-shadow: 0 0 0 .7em #1f9d551f; }
.panel--done h4 { margin-bottom: .3em; }
.panel--done p { margin: 0 0 1.6em; font-size: .85em; line-height: 1.45; color: var(--vb-muted); }
.quota { display: inline-flex; align-items: center; gap: .35em; padding: .55em .9em; border-radius: 99px; background: var(--vb-paper); font-size: .75em; font-weight: 600; }
.quota i { width: .55em; height: .55em; border-radius: 50%; background: var(--vb-line); }
.quota i.on { background: var(--vb-ink); }
.quota i:last-of-type { margin-right: .3em; }

.is-playing .cat--pick { animation: pick var(--loop) infinite; }
.is-playing .tap--1 { animation: tap1 var(--loop) infinite; }
.is-playing .tap--2 { animation: tap2 var(--loop) infinite; }
.is-playing .panel--sub { animation: sub var(--loop) var(--vb-ease) infinite; }
.is-playing .row--pick { animation: pickrow var(--loop) infinite; }
.is-playing .row--pick i { animation: radio var(--loop) infinite; }
.is-playing .panel--done { animation: done var(--loop) var(--vb-ease) infinite; }

@keyframes tap1 { 0%, 12% { opacity: 0; transform: scale(.4); } 15% { opacity: 1; transform: scale(1); } 20%, 100% { opacity: 0; transform: scale(1.4); } }
@keyframes pick { 0%, 14% { background: var(--vb-paper); color: inherit; } 16%, 100% { background: var(--vb-ink); color: #fff; } }
@keyframes sub { 0%, 22% { transform: translateX(100%); } 28%, 100% { transform: translateX(0); } }
@keyframes tap2 { 0%, 38% { opacity: 0; transform: scale(.4); } 41% { opacity: 1; transform: scale(1); } 46%, 100% { opacity: 0; transform: scale(1.4); } }
@keyframes pickrow { 0%, 41% { box-shadow: none; } 43%, 100% { box-shadow: inset 0 0 0 2px var(--vb-ink); background: #fff; } }
@keyframes radio { 0%, 41% { box-shadow: inset 0 0 0 2px var(--vb-line); } 43%, 100% { box-shadow: inset 0 0 0 .35em var(--vb-ink); } }
@keyframes done { 0%, 56% { opacity: 0; transform: scale(.96); } 61%, 92% { opacity: 1; transform: none; } 98%, 100% { opacity: 0; } }

</style>
