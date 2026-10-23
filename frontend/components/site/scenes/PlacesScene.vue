<template>
  <div class="scene" :class="{ 'is-playing': playing }">
    <h4>{{ p.title }}</h4>
    <ul class="places">
      <li v-for="(place, index) in p.list" :key="place.name">
        <span class="pin" :class="`pin--${index}`" />
        <span><b>{{ place.name }}</b><small>{{ place.area }} · {{ place.r }}</small></span>
        <span class="switch" :class="{ 'switch--anim': index === 2 }"><i /></span>
      </li>
    </ul>

    <p class="label">{{ p.filters }}</p>
    <div class="chips">
      <span v-for="(chip, index) in p.chips" :key="chip" class="chip" :class="{ on: [0, 1, 2, 4].includes(index), 'chip--anim': index === 3, on2: index === 3 }">{{ chip }}</span>
    </div>

    <p class="label">{{ p.check }}</p>
    <div class="segment">
      <span v-for="option in p.every" :key="option">{{ option }}</span>
      <i class="thumb" />
    </div>

    <ul class="places places--rows">
      <li v-for="row in p.rows" :key="row.label"><span><b>{{ row.label }}</b></span><small>{{ row.value }}</small><svg viewBox="0 0 24 24" width="13" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="m9 18 6-6-6-6" /></svg></li>
    </ul>
  </div>
</template>

<script setup lang="ts">
import type { SiteCopy } from '~/locales/site';

defineProps<{ p: SiteCopy['scene']['placesScene'] }>();
const playing = useScenePlaying();
</script>

<style scoped>
.scene { position: absolute; inset: 0; --loop: 8s; padding: 22% 6.5% 0; background: var(--vb-paper); }
h4 { margin: 0 0 .8em; font-size: 1.45em; font-weight: 680; letter-spacing: -.035em; }
.places { margin: 0 0 1.3em; padding: .2em .9em; list-style: none; border-radius: 1.1em; background: #fff; }
.places li { display: flex; align-items: center; gap: .7em; padding: .7em 0; }
.places li + li { border-top: 1px solid var(--vb-line-2); }
.places li > span:nth-child(2) { flex: 1; display: grid; }
.places b { font-size: .85em; font-weight: 620; }
.places small { font-size: .7em; color: var(--vb-muted); }
.pin { width: 2em; height: 2em; border-radius: .7em; background: var(--vb-brand-50); position: relative; }
.pin::after { content: ''; position: absolute; inset: 35%; border-radius: 50%; background: var(--vb-brand); }
.switch { position: relative; width: 2.3em; height: 1.35em; border-radius: 99px; background: var(--vb-line); }
.switch i { position: absolute; top: .15em; left: .15em; width: 1.05em; height: 1.05em; border-radius: 50%; background: #fff; box-shadow: 0 1px 3px #0003; transition: none; }
.switch { background: var(--vb-brand); }
.switch i { left: 1.1em; }
.places--rows { margin-top: 1.3em; }
.places--rows li { padding: .8em 0; }
.places--rows small { font-size: .74em; }
.places--rows svg { color: var(--vb-subtle); }
.label { margin: 0 0 .55em .2em; font-size: .7em; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--vb-muted); }
.chips { display: flex; flex-wrap: wrap; gap: .4em; margin-bottom: 1.3em; }
.chip { padding: .5em .85em; border-radius: 99px; background: #fff; box-shadow: inset 0 0 0 1.5px var(--vb-line); font-size: .76em; font-weight: 580; }
.chip.on, .chip.on2 { background: var(--vb-ink); color: #fff; box-shadow: none; }
.segment { position: relative; display: grid; grid-template-columns: repeat(3, 1fr); padding: .25em; border-radius: .9em; background: var(--vb-line-2); }
.segment span { position: relative; z-index: 1; padding: .6em 0; text-align: center; font-size: .74em; font-weight: 600; }
.thumb { position: absolute; top: .25em; bottom: .25em; left: .25em; width: calc((100% - .5em) / 3); border-radius: .7em; background: #fff; box-shadow: 0 1px 4px #0b0d121f; transform: translateX(100%); }

.is-playing .switch--anim { animation: sw var(--loop) infinite; }
.is-playing .switch--anim i { animation: swi var(--loop) var(--vb-ease) infinite; }
.is-playing .chip--anim { animation: chip var(--loop) infinite; }
.is-playing .thumb { animation: thumb var(--loop) var(--vb-ease) infinite; }

@keyframes sw { 0%, 20% { background: var(--vb-line); } 24%, 100% { background: var(--vb-brand); } }
@keyframes swi { 0%, 20% { left: .15em; } 24%, 100% { left: 1.1em; } }
@keyframes chip { 0%, 44% { background: #fff; color: inherit; } 48%, 100% { background: var(--vb-ink); color: #fff; } }
@keyframes thumb { 0%, 64% { transform: translateX(200%); } 70%, 100% { transform: translateX(100%); } }

</style>
