<template>
  <section ref="root" class="alert-examples" role="region" aria-roledescription="carousel" aria-labelledby="examples-heading" @mouseenter="hovering = true" @mouseleave="hovering = false" @focusin="focused = true" @focusout="onFocusOut" @keydown.left.prevent="select(active - 1)" @keydown.right.prevent="select(active + 1)">
    <div class="examples-wrap">
      <header class="examples-heading">
        <div><p class="examples-eyebrow">{{ copy.eyebrow }}</p><h2 id="examples-heading">{{ copy.title }}</h2></div>
        <p>{{ copy.lead }}</p>
      </header>
      <div class="examples-stage" :class="{ 'is-playing': playing }" :style="{ '--scene-accent': scenes[active]!.color }">
        <div v-for="(scene, index) in scenes" :key="scene.image" class="examples-image" :class="{ 'is-active': active === index }" aria-hidden="true">
          <img :src="scene.image" alt="" width="1600" height="900" loading="lazy" decoding="async" />
        </div>
        <div class="examples-shade" aria-hidden="true" />
        <div class="examples-top"><span class="example-label"><i />{{ copy.example }}</span><span class="examples-counter">0{{ active + 1 }} <i>/ 0{{ scenes.length }}</i></span></div>
        <div class="examples-content" :aria-live="playing ? 'off' : 'polite'" aria-atomic="true">
          <div class="example-story-wrap"><Transition name="example-copy" mode="out-in">
            <div :key="active" class="example-story">
              <p class="example-tag"><Icon :name="scenes[active]!.icon" :size="16" />{{ current.tag }}</p>
              <h3>{{ current.title }}</h3>
              <div class="example-benefit"><span>{{ copy.benefit }}</span><p>{{ current.text }}</p></div>
            </div>
          </Transition></div>
          <div class="example-notification" aria-hidden="true">
            <div class="example-notification__bar"><LogoMark :size="28" /><span>VEILIGERBUITEN<small>{{ copy.reported }}</small></span><Icon name="bell" :size="16" /></div>
            <div class="example-notification__body"><span class="example-notification__icon"><Icon :name="scenes[active]!.icon" :size="21" /></span><div><strong>{{ current.title }}</strong><small>{{ current.meta }}</small></div></div>
            <div class="example-notification__foot"><i />{{ current.category }}<span>↗</span></div>
          </div>
        </div>
        <div class="examples-bottom">
          <div class="examples-picker" :aria-label="copy.select">
            <button v-for="(item, index) in copy.items" :key="scenes[index]!.image" type="button" :class="{ 'is-active': active === index }" :aria-pressed="active === index" :aria-label="`${copy.select}: ${item.title}`" @click="select(index)"><Icon :name="scenes[index]!.icon" :size="16" /><span>{{ item.category }}</span><i :style="{ transform: `scaleX(${active === index ? progress.value : 0})` }" /></button>
          </div>
          <div class="examples-controls">
            <button type="button" :aria-label="copy.previous" @click="select(active - 1)"><Icon name="back" :size="17" /></button>
            <button v-if="!reduced" type="button" :aria-label="paused ? copy.play : copy.pause" :aria-pressed="paused" @click="paused = !paused"><svg viewBox="0 0 20 20" width="16" fill="currentColor" aria-hidden="true"><path v-if="paused" d="m6 3 10 7-10 7Z" /><path v-else d="M5 3h3v14H5zM12 3h3v14h-3z" /></svg></button>
            <button type="button" :aria-label="copy.next" @click="select(active + 1)"><Icon name="chevron" :size="17" /></button>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import type { SiteCopy } from '~/locales/site';
import type { IconName } from '~/utils/icons';
const props = defineProps<{ copy: SiteCopy['examples'] }>();
const scenes: Array<{ image: string; color: string; icon: IconName }> = [
  { image: '/images/alerts/bridge.webp', color: '#ffba66', icon: 'alert-triangle' },
  { image: '/images/alerts/pickpockets.webp', color: '#ffba66', icon: 'shield-alert' },
  { image: '/images/alerts/fire.webp', color: '#ff888b', icon: 'fire' },
  { image: '/images/alerts/crowd.webp', color: '#b0c9ff', icon: 'users' },
  { image: '/images/alerts/police.webp', color: '#b0c9ff', icon: 'info' },
];
const root = ref<HTMLElement | null>(null);
const active = ref(0);
const paused = ref(false);
const hovering = ref(false);
const focused = ref(false);
const visible = ref(false);
const documentVisible = ref(true);
const reduced = ref(false);
const progress = reactive({ value: 0 });
const current = computed(() => props.copy.items[active.value]!);
const playing = computed(() => visible.value && documentVisible.value && !paused.value && !hovering.value && !focused.value && !reduced.value);
let observer: IntersectionObserver | undefined;
let media: MediaQueryList | undefined;
let gsapApi: typeof import('gsap')['gsap'] | undefined;
let tween: ReturnType<typeof import('gsap')['gsap']['to']> | undefined;
let disposed = false;
function select(index: number) {
  const next = (index + scenes.length) % scenes.length;
  if (next === active.value) resetProgress();
  else active.value = next;
}
function onFocusOut(event: FocusEvent) { if (!root.value?.contains(event.relatedTarget as Node | null)) focused.value = false; }
function onVisibility() { documentVisible.value = !document.hidden; }
function onMotion() { reduced.value = !!media?.matches; resetProgress(); }
function resetProgress() {
  tween?.kill();
  progress.value = 0;
  if (!gsapApi || reduced.value || disposed) return;
  tween = gsapApi.to(progress, { value: 1, duration: 6, ease: 'none', paused: !playing.value, onComplete: () => select(active.value + 1) });
}
watch(active, resetProgress);
watch(playing, (play) => { if (play) tween?.resume(); else tween?.pause(); });
onMounted(async () => {
  media = window.matchMedia('(prefers-reduced-motion: reduce)');
  reduced.value = media.matches;
  media.addEventListener('change', onMotion);
  onVisibility();
  document.addEventListener('visibilitychange', onVisibility);
  observer = new IntersectionObserver(([entry]) => { visible.value = !!entry?.isIntersecting; }, { threshold: .25 });
  if (root.value) observer.observe(root.value);
  const { gsap } = await import('gsap');
  if (disposed) return;
  gsapApi = gsap;
  resetProgress();
});
onBeforeUnmount(() => { disposed = true; tween?.kill(); observer?.disconnect(); media?.removeEventListener('change', onMotion); document.removeEventListener('visibilitychange', onVisibility); });
</script>

<style scoped>
.alert-examples { background: #f9fafb; padding: 0 0 108px; }
.examples-wrap { width: min(1200px, calc(100% - 48px)); margin: 0 auto; }
.examples-heading { display: flex; justify-content: space-between; align-items: end; gap: 50px; margin-bottom: 35px; }
.examples-eyebrow { margin: 0 0 18px; color: #2f6bff; font: 10px var(--vb-mono); letter-spacing: .05em; text-transform: uppercase; }
.examples-heading h2 { margin: 0; white-space: pre-line; font-size: clamp(38px,4.6vw,62px); font-weight: 570; line-height: 1.04; letter-spacing: -.06em; }
.examples-heading > p { max-width: 330px; color: #657185; font-size: 15px; line-height: 1.7; margin: 0 0 3px; }
.examples-stage { position: relative; isolation: isolate; overflow: hidden; border-radius: 25px; min-height: 575px; padding: 28px 36px 24px; color: #fff; background: #162031; display: flex; flex-direction: column; }
.examples-image, .examples-shade { position: absolute; inset: 0; z-index: -2; }
.examples-image { opacity: 0; transition: opacity 1s; }
.examples-image.is-active { opacity: 1; }
.examples-image img { width: 100%; height: 100%; object-fit: cover; object-position: 55% 50%; }
.examples-image.is-active img { animation: image-drift 8s ease-out both; animation-play-state: paused; }
.is-playing .examples-image.is-active img { animation-play-state: running; }
.examples-shade { z-index: -1; background: linear-gradient(90deg,#0b1526ed 0%,#0b1526a8 40%,#0b15261a 100%),linear-gradient(0deg,#0b1526e8 0%,transparent 50%,#0b152644 100%); }
.examples-top { display: flex; align-items: center; justify-content: space-between; }
.example-label { display: inline-flex; align-items: center; gap: 8px; font: 9px var(--vb-mono); text-transform: uppercase; letter-spacing: .06em; padding: 9px 12px; background: #ffffff0e; border: 1px solid #ffffff30; border-radius: 99px; backdrop-filter: blur(10px); }
.example-label i { width: 5px; height: 5px; background: #a5bbdb; border-radius: 50%; }
.examples-counter { font: 11px var(--vb-mono); letter-spacing: .08em; }
.examples-counter i { font-style: normal; color: #b6c4d6; }
.examples-content { display: grid; grid-template-columns: 1fr .85fr; align-items: center; gap: 60px; flex: 1; padding: 45px 0; }
.example-story { max-width: 490px; }
.example-tag { display: flex; align-items: center; gap: 8px; margin: 0 0 17px; color: var(--scene-accent); font-size: 12px; font-weight: 500; }
.example-story h3 { margin: 0; font-size: clamp(30px,3.2vw,45px); font-weight: 550; line-height: 1.05; letter-spacing: -.045em; }
.example-benefit { margin-top: 30px; padding-top: 18px; border-top: 1px solid #ffffff30; }
.example-benefit > span { color: #bbc8da; text-transform: uppercase; letter-spacing: .05em; font: 9px var(--vb-mono); }
.example-benefit p { margin: 9px 0 0; color: #dde4ef; font-size: 14px; line-height: 1.75; max-width: 375px; }
.example-notification { justify-self: end; width: 100%; max-width: 340px; padding: 17px; border: 1px solid #ffffff8a; border-radius: 19px; background: #fffffff0; backdrop-filter: blur(20px); color: #142037; transform: rotate(3deg); box-shadow: 0 20px 60px #0003; }
.example-notification__bar { display: flex; align-items: center; gap: 9px; margin-bottom: 19px; }
.example-notification__bar > span { flex: 1; font-size: 8px; font-weight: 600; letter-spacing: .04em; color: #617087; }
.example-notification__bar small { display: block; color: #8c98a9; font-weight: 400; font-size: 8px; letter-spacing: 0; margin-top: 2px; }
.example-notification__bar > :last-child { color: #8896ac; }
.example-notification__body { display: flex; align-items: start; gap: 12px; }
.example-notification__icon { width: 38px; height: 38px; display: grid; place-items: center; flex: 0 0 auto; background: #e7edf8; color: #4a668e; border-radius: 11px; }
.example-notification__body strong { font-size: 14px; line-height: 1.35; letter-spacing: -.025em; font-weight: 600; display: block; }
.example-notification__body small { color: #748198; font-size: 10px; line-height: 1.5; margin-top: 5px; display: block; }
.example-notification__foot { display: flex; gap: 6px; align-items: center; margin-top: 19px; padding-top: 12px; border-top: 1px solid #dce2eb; color: #6b7c95; font-size: 9px; }
.example-notification__foot > i { width: 5px; height: 5px; border-radius: 50%; background: #839bc0; }
.example-notification__foot > span { margin-left: auto; font-size: 17px; }
.examples-bottom { display: flex; gap: 25px; align-items: center; justify-content: space-between; padding-top: 22px; border-top: 1px solid #ffffff26; }
.examples-picker { display: flex; gap: 7px; flex-wrap: wrap; }
.examples-picker button { position: relative; display: inline-flex; align-items: center; justify-content: center; gap: 7px; padding: 11px 13px; border-radius: 8px; border: 1px solid #ffffff26; background: #ffffff05; color: #e2e8f2; font: 11px var(--vb-font); overflow: hidden; transition: background .2s,border-color .2s; }
.examples-picker button.is-active { background: #ffffff1c; border-color: #ffffff80; color: #fff; }
.examples-picker button > i { position: absolute; height: 2px; inset: auto 0 0; background: var(--scene-accent); transform-origin: left; }
.examples-controls { display: flex; gap: 6px; flex-shrink: 0; }
.examples-controls button { width: 34px; height: 34px; display: grid; place-items: center; border: 1px solid #ffffff40; border-radius: 50%; background: #ffffff08; color: #fff; transition: background .2s; }
.examples-controls button:hover, .examples-picker button:hover { background: #ffffff26; }
button:focus-visible { outline: 3px solid #acc6ff; outline-offset: 4px; }
.example-copy-enter-active, .example-copy-leave-active { transition: opacity .25s, transform .25s; }
.example-copy-enter-from { opacity: 0; transform: translateY(8px); }
.example-copy-leave-to { opacity: 0; transform: translateY(-8px); }
@keyframes image-drift { from { transform: scale(1); } to { transform: scale(1.055); } }
@media (max-width: 979px) { .examples-content { gap: 30px; } .examples-stage { padding-inline: 28px; min-height: 580px; } .examples-picker { gap: 5px; } .examples-picker button { padding: 10px; } .examples-picker button span { display: none; } }
@media (max-width: 767px) { .alert-examples { padding-bottom: 72px; } .examples-wrap { width: calc(100% - 44px); } .examples-heading { display: block; margin-bottom: 28px; } .examples-heading > p { margin-top: 18px; font-size: 14px; } .examples-heading h2 { font-size: 40px; } .examples-stage { padding: 21px; border-radius: 20px; min-height: 700px; } .examples-content { grid-template-columns: 1fr; padding: 32px 0; gap: 28px; } .example-story h3 { font-size: 33px; min-height: 105px; } .example-benefit { margin-top: 20px; } .example-benefit p { font-size: 13px; min-height: 90px; } .example-notification { max-width: 285px; justify-self: center; padding: 14px; } .example-notification__bar { margin-bottom: 15px; } .examples-bottom { flex-wrap: wrap; gap: 15px; padding-top: 19px; } .examples-picker { gap: 5px; } .examples-picker button { padding: 9px; } .examples-controls { margin-left: auto; } .examples-controls button { width: 29px; height: 29px; } .examples-image img { object-position: 58% 50%; } .examples-shade { background: linear-gradient(180deg,#0b1526c9,#0b1526a8 45%,#0b152650 70%,#0b1526eb); } }
@media (max-width: 374px) { .examples-wrap { width: calc(100% - 34px); } .examples-stage { padding: 18px; } .example-story h3 { font-size: 29px; } }
@media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation: none !important; transition: none !important; } }
</style>
