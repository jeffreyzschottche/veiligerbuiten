<template>
  <div class="scene" :class="{ 'is-playing': playing }">
    <MiniMap />

    <span class="dot dot--info" style="--x: 72%; --y: 46%; --s: 1" />
    <span class="dot dot--warn" style="--x: 24%; --y: 40%; --s: 1.25" />
    <span class="dot dot--warn" style="--x: 38%; --y: 58%; --s: 1" />
    <span class="user" style="--x: 52%; --y: 32%" />
    <span class="dot dot--danger dot--live" style="--x: 50%; --y: 23%; --s: 1.7"><i /></span>

    <div class="top">
      <span class="pill"><svg viewBox="0 0 24 24" width="13"><path d="M12 22s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12Z" /><circle cx="12" cy="10" r="2.5" fill="#fff" /></svg>{{ s.here }}</span>
      <span class="round"><svg viewBox="0 0 24 24" width="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M4 6h16M7 12h10M10 18h4" /></svg></span>
    </div>

    <div class="notif">
      <LogoMark :size="30" />
      <div>
        <p><b>VEILIGERBUITEN</b><span>{{ s.now }}</span></p>
        <strong>{{ s.notifTitle }}</strong>
        <small>{{ s.notifBody }}</small>
      </div>
    </div>

    <span class="fab"><svg viewBox="0 0 24 24" width="22" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14" /></svg></span>

    <div class="sheet">
      <i class="handle" />
      <p class="sheet__head"><b>{{ s.nearby }}</b><span>3</span></p>
      <ul>
        <li v-for="(item, index) in s.items" :key="item.title" :class="{ 'is-new': index === 0 }">
          <span class="tone" :class="`tone--${item.tone}`" />
          <span><b>{{ item.title }}</b><small>{{ item.meta }}</small></span>
          <svg viewBox="0 0 24 24" width="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="m9 18 6-6-6-6" /></svg>
        </li>
      </ul>
      <nav>
        <span v-for="(tab, index) in s.tabs" :key="tab" :class="{ active: index === 0 }"><i />{{ tab }}</span>
      </nav>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { SiteCopy } from '~/locales/site';

defineProps<{ s: SiteCopy['scene'] }>();
const playing = useScenePlaying();
</script>

<style scoped>
.scene { position: absolute; inset: 0; --loop: 9s; }
.top { position: absolute; z-index: 5; top: 12.5%; left: 5.5%; right: 5.5%; display: flex; justify-content: space-between; }
.pill, .round { display: inline-flex; align-items: center; gap: .4em; height: 2.6em; padding: 0 1em; border-radius: 99px; background: #fff; box-shadow: var(--vb-shadow); font-size: .82em; font-weight: 600; }
.pill svg { fill: var(--vb-brand); }
.round { justify-content: center; width: 2.6em; padding: 0; }

.dot { position: absolute; z-index: 2; left: var(--x); top: var(--y); width: calc(.85em * var(--s)); height: calc(.85em * var(--s)); transform: translate(-50%, -50%); border: 2px solid #fff; border-radius: 50%; box-shadow: 0 2px 6px #0b0d1240; }
.dot--info { background: var(--vb-info); }
.dot--warn { background: var(--vb-warn); }
.dot--danger { background: var(--vb-danger); }
.dot--live i { position: absolute; inset: -2px; border-radius: 50%; background: var(--vb-danger); z-index: -1; }
.user { position: absolute; z-index: 2; left: var(--x); top: var(--y); width: .9em; height: .9em; transform: translate(-50%, -50%); border: 3px solid #fff; border-radius: 50%; background: var(--vb-brand); box-shadow: 0 0 0 .8em #2f6bff24; }

.notif { position: absolute; z-index: 20; top: 10.5%; left: 3.5%; right: 3.5%; display: flex; gap: .7em; padding: .8em .9em; border-radius: 1.4em; background: #ffffffe6; backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); box-shadow: 0 10px 30px #0b0d1230; }
.notif :deep(svg) { border-radius: 8px; }
.notif div { min-width: 0; }
.notif p { display: flex; justify-content: space-between; margin: 0 0 .15em; font-size: .64em; letter-spacing: .04em; color: var(--vb-muted); }
.notif p b { font-weight: 600; }
.notif strong { display: block; font-size: .84em; font-weight: 640; letter-spacing: -.01em; line-height: 1.25; }
.notif small { display: block; margin-top: .1em; font-size: .74em; color: var(--vb-muted); }

.fab { position: absolute; z-index: 6; right: 5.5%; bottom: 43.5%; display: grid; place-items: center; width: 3.1em; height: 3.1em; border-radius: 50%; color: #fff; background: var(--vb-ink); box-shadow: 0 8px 20px #0b0d1240; }

.sheet { position: absolute; z-index: 8; left: 0; right: 0; bottom: 0; height: 41%; padding: .5em 5.5% 0; border-radius: 1.6em 1.6em 0 0; background: #fff; box-shadow: 0 -8px 30px #0b0d1218; display: flex; flex-direction: column; }
.handle { display: block; width: 2.6em; height: 4px; margin: 0 auto .7em; border-radius: 9px; background: var(--vb-line); }
.sheet__head { display: flex; align-items: center; gap: .5em; margin: 0 0 .4em; font-size: .9em; }
.sheet__head b { font-weight: 650; letter-spacing: -.02em; }
.sheet__head span { padding: .1em .55em; border-radius: 99px; background: var(--vb-paper); font-size: .78em; font-weight: 600; color: var(--vb-muted); }
ul { margin: 0; padding: 0; list-style: none; }
li { display: flex; align-items: center; gap: .7em; padding: .55em 0; border-bottom: 1px solid var(--vb-line-2); }
li > span:nth-child(2) { flex: 1; display: grid; }
li b { font-size: .8em; font-weight: 600; }
li small { font-size: .68em; color: var(--vb-muted); font-variant-numeric: tabular-nums; }
li svg { color: var(--vb-subtle); }
.tone { width: .7em; height: .7em; border-radius: 50%; }
.tone--danger { background: var(--vb-danger); box-shadow: 0 0 0 .25em #e5484d22; }
.tone--warn { background: var(--vb-warn); }
.tone--info { background: var(--vb-info); }
nav { display: flex; justify-content: space-around; margin-top: auto; padding: .5em 0 1.6em; border-top: 1px solid var(--vb-line-2); }
nav span { display: grid; justify-items: center; gap: .3em; font-size: .62em; font-weight: 550; color: var(--vb-subtle); }
nav span i { width: 1.9em; height: 1.9em; border-radius: .6em; background: var(--vb-line-2); }
nav span.active { color: var(--vb-ink); }
nav span.active i { background: var(--vb-ink); }

.is-playing .dot--live { animation: drop var(--loop) var(--vb-ease) infinite; }
.is-playing .dot--live i { animation: ping 1.8s ease-out infinite; }
.is-playing .notif { animation: notif var(--loop) var(--vb-ease) infinite; }
.is-playing li.is-new { animation: newrow var(--loop) var(--vb-ease) infinite; }

@keyframes ping { 0% { transform: scale(1); opacity: .55; } 100% { transform: scale(3.4); opacity: 0; } }
@keyframes drop { 0%, 18% { transform: translate(-50%, -50%) scale(0); } 24% { transform: translate(-50%, -50%) scale(1.25); } 28%, 100% { transform: translate(-50%, -50%) scale(1); } }
@keyframes notif { 0%, 26% { transform: translateY(-140%); opacity: 0; } 31%, 80% { transform: translateY(0); opacity: 1; } 86%, 100% { transform: translateY(-140%); opacity: 0; } }
@keyframes newrow { 0%, 20% { opacity: 0; transform: translateY(-.6em); max-height: 0; padding: 0; } 26%, 100% { opacity: 1; transform: none; max-height: 4em; } }

</style>
