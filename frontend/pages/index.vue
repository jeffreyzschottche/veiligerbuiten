<template>
  <div class="site">
    <header class="nav" :class="{ 'nav--scrolled': scrolled }">
      <div class="wrap nav__inner">
        <a href="#top" class="nav__logo" aria-label="VeiligBuiten"><Logo :size="30" /></a>
        <nav class="nav__links" aria-label="Secties">
          <a href="#how">{{ t.nav.how }}</a>
          <a href="#alerts">{{ t.nav.alerts }}</a>
          <a href="#ai">{{ t.nav.ai }}</a>
          <a href="#price">{{ t.nav.price }}</a>
        </nav>
        <div class="nav__end">
          <button class="lang" type="button" :aria-label="locale === 'nl' ? 'Switch to English' : 'Naar Nederlands'" @click="setLocale(locale === 'nl' ? 'en' : 'nl')">
            <span :class="{ on: locale === 'nl' }">NL</span><span :class="{ on: locale === 'en' }">EN</span>
          </button>
          <a href="#download" class="btn btn--ink">{{ t.nav.download }}</a>
        </div>
      </div>
    </header>

    <main id="top">
      <!-- Hero -->
      <section class="hero">
        <svg class="hero__rings" viewBox="0 0 800 800" aria-hidden="true">
          <circle v-for="r in [120, 200, 280, 360]" :key="r" cx="400" cy="400" :r="r" />
        </svg>
        <div class="wrap hero__grid">
          <div class="hero__copy">
            <p class="eyebrow"><span class="live" />{{ t.hero.eyebrow }}</p>
            <h1>{{ t.hero.title }}</h1>
            <p class="lead">{{ t.hero.lead }}</p>
            <StoreButtons :copy="t" />
            <p class="hero__price">{{ t.hero.price }}</p>
          </div>
          <div class="hero__visual">
            <PhoneFrame class="hero__phone"><MapScene :s="t.scene" /></PhoneFrame>
            <span class="float float--a"><i class="tone tone--danger" />{{ t.hero.chipConfirmed }}</span>
            <span class="float float--b"><i class="tone tone--info" />{{ t.hero.chipSource }}</span>
          </div>
        </div>
      </section>

      <!-- Bronnen -->
      <section class="sources" aria-label="Bronnen">
        <div class="wrap sources__inner">
          <p class="sources__label"><b>{{ sourceNames.length }}</b> {{ t.sources.label }}</p>
          <div class="marquee">
            <div class="marquee__track">
              <span v-for="(name, index) in [...sourceNames, ...sourceNames]" :key="index" :aria-hidden="index >= sourceNames.length">{{ name }}</span>
            </div>
          </div>
          <p class="sources__users">{{ t.sources.users }}</p>
        </div>
      </section>

      <!-- Hoe het werkt -->
      <section id="how" class="section">
        <div class="wrap">
          <header class="section__head">
            <p class="eyebrow">{{ t.how.eyebrow }}</p>
            <h2>{{ t.how.title }}</h2>
          </header>
          <ol class="steps">
            <li v-for="(step, index) in t.how.steps" :key="step.title" class="step">
              <div class="step__stage">
                <PhoneFrame class="step__phone">
                  <MapScene v-if="index === 0" :s="t.scene" />
                  <ReportScene v-else-if="index === 1" :r="t.scene.report" />
                  <ConfirmScene v-else :c="t.scene.confirm" />
                </PhoneFrame>
              </div>
              <p class="step__num">0{{ index + 1 }}</p>
              <h3>{{ step.title }}</h3>
              <p>{{ step.text }}</p>
            </li>
          </ol>
        </div>
      </section>

      <!-- Meldingen -->
      <section id="alerts" class="section section--ink">
        <div class="wrap split">
          <div class="split__copy">
            <p class="eyebrow eyebrow--light">{{ t.alerts.eyebrow }}</p>
            <h2 class="pre">{{ t.alerts.title }}</h2>
            <p class="lead lead--light">{{ t.alerts.lead }}</p>
            <ul class="levels">
              <li v-for="(point, index) in t.alerts.points" :key="point.title">
                <span class="level" :class="`level--${['danger', 'warn', 'info'][index]}`" />
                <div><b>{{ point.title }}</b><span>{{ point.text }}</span></div>
              </li>
            </ul>
          </div>
          <div class="split__visual">
            <PhoneFrame dark time="21:47"><LockScene :l="t.scene.lock" :now="t.scene.now" /></PhoneFrame>
          </div>
        </div>
      </section>

      <!-- Plekken -->
      <section class="section">
        <div class="wrap split split--flip">
          <div class="split__visual">
            <PhoneFrame><PlacesScene :p="t.scene.placesScene" /></PhoneFrame>
          </div>
          <div class="split__copy">
            <p class="eyebrow">{{ t.places.eyebrow }}</p>
            <h2 class="pre">{{ t.places.title }}</h2>
            <p class="lead">{{ t.places.lead }}</p>
            <ul class="checks">
              <li v-for="point in t.places.points" :key="point"><svg viewBox="0 0 24 24" width="18" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6" /></svg>{{ point }}</li>
            </ul>
          </div>
        </div>
      </section>

      <!-- AI -->
      <section id="ai" class="section section--paper2">
        <div class="wrap split">
          <div class="split__copy">
            <p class="eyebrow">{{ t.ai.eyebrow }}</p>
            <h2 class="pre">{{ t.ai.title }}</h2>
            <p class="lead">{{ t.ai.lead }}</p>
            <dl class="endpoint">
              <div><dt>{{ t.ai.endpoint }}</dt><dd>{{ mcpUrl }}</dd></div>
              <div><dt>{{ t.ai.webhook }}</dt><dd>{{ t.ai.webhookValue }}</dd></div>
            </dl>
          </div>
          <div class="split__visual">
            <div ref="chatEl" class="chat" :class="{ 'is-playing': chatPlaying }">
              <p class="chat__bar"><i /><i /><i /><span>{{ t.ai.chat.assistant }}</span></p>
              <div class="chat__body">
                <p class="bubble bubble--me">{{ t.ai.chat.question }}</p>
                <p class="tool"><LogoMark :size="18" />{{ t.ai.chat.tool }}<span class="tool__ok">✓</span></p>
                <p class="bubble bubble--ai">{{ t.ai.chat.answer }}</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Vertrouwen -->
      <section class="trust">
        <div class="wrap trust__grid">
          <article v-for="(item, index) in t.trust" :key="item.title">
            <span class="trust__icon" :class="{ 'trust__icon--sos': index === 2 }">
              <svg v-if="index === 0" viewBox="0 0 24 24" width="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 22s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12Z" /><circle cx="12" cy="10" r="2.5" /></svg>
              <svg v-else-if="index === 1" viewBox="0 0 24 24" width="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="2" y="7" width="17" height="10" rx="2.5" /><path d="M22 11v2M6 10v4M9.5 10v4" /></svg>
              <b v-else>112</b>
            </span>
            <h3>{{ item.title }}</h3>
            <p>{{ item.text }}</p>
          </article>
        </div>
      </section>

      <!-- Prijs -->
      <section id="price" class="section price-section">
        <div class="wrap">
          <div id="download" class="price">
            <div class="price__left">
              <p class="eyebrow eyebrow--light">{{ t.price.eyebrow }}</p>
              <p class="price__amount">{{ t.price.amount }}<span>{{ t.price.per }}</span></p>
              <h2>{{ t.price.title }}</h2>
              <StoreButtons :copy="t" light />
              <p class="price__note">{{ t.price.note }}</p>
            </div>
            <ul class="price__list">
              <li v-for="item in t.price.items" :key="item"><svg viewBox="0 0 24 24" width="18" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6" /></svg>{{ item }}</li>
            </ul>
            <LogoMark class="price__mark" :size="320" inverse />
          </div>
        </div>
      </section>
    </main>

    <footer class="footer">
      <div class="wrap footer__inner">
        <div>
          <Logo :size="26" />
          <p class="footer__motto">{{ t.footer.motto }}</p>
        </div>
        <p class="footer__sos">{{ t.footer.emergency }}</p>
        <nav class="footer__links">
          <a href="/privacy">{{ t.footer.privacy }}</a>
          <a href="/voorwaarden">{{ t.footer.terms }}</a>
          <a href="mailto:hallo@veiligbuiten.nl">{{ t.footer.contact }}</a>
          <span>© {{ year }} VeiligBuiten</span>
        </nav>
      </div>
    </footer>
  </div>
</template>

<script setup lang="ts">
import { siteCopy, sourceNames } from '~/locales/site';

definePageMeta({ layout: false });

const { locale, setLocale } = useLocale();
const t = computed(() => siteCopy[locale.value]);
const mcpUrl = useRuntimeConfig().public.mcpUrl;
const year = new Date().getFullYear();
const chatEl = ref<HTMLElement | null>(null);
const chatPlaying = usePlayInView(chatEl);

useHead(() => ({
  htmlAttrs: { lang: locale.value },
  title: t.value.meta.title,
  meta: [
    { name: 'description', content: t.value.meta.description },
    { property: 'og:title', content: t.value.meta.title },
    { property: 'og:description', content: t.value.meta.description },
    { name: 'theme-color', content: '#f4f5f7' },
  ],
}));

const scrolled = ref(false);
function onScroll() { scrolled.value = window.scrollY > 8; }
onMounted(() => { onScroll(); window.addEventListener('scroll', onScroll, { passive: true }); });
onBeforeUnmount(() => window.removeEventListener('scroll', onScroll));
</script>

<style scoped>
.site { --gutter: 20px; min-height: 100vh; background: var(--vb-paper); color: var(--vb-ink); overflow-x: clip; }
.wrap { width: min(1180px, 100% - var(--gutter) * 2); margin: 0 auto; }
@media (min-width: 768px) { .site { --gutter: 32px; } }

/* Nav */
.nav { position: sticky; top: 0; z-index: 50; transition: background .25s, box-shadow .25s; }
.nav--scrolled { background: #f4f5f7d9; backdrop-filter: saturate(1.4) blur(16px); -webkit-backdrop-filter: saturate(1.4) blur(16px); box-shadow: 0 1px 0 var(--vb-line); }
.nav__inner { display: flex; align-items: center; gap: 24px; height: 68px; }
.nav__logo { text-decoration: none; }
.nav__links { display: none; gap: 28px; margin: 0 auto; }
.nav__links a { color: var(--vb-muted); font-size: 15px; font-weight: 500; text-decoration: none; transition: color .15s; }
.nav__links a:hover { color: var(--vb-ink); }
.nav__end { display: flex; align-items: center; gap: 10px; margin-left: auto; }
@media (min-width: 900px) { .nav__links { display: flex; } .nav__end { margin-left: 0; } }
.lang { display: inline-flex; padding: 3px; border: 0; border-radius: 10px; background: var(--vb-line-2); font: 600 12px var(--vb-mono); }
.lang span { padding: 6px 8px; border-radius: 7px; color: var(--vb-subtle); }
.lang span.on { background: #fff; color: var(--vb-ink); box-shadow: 0 1px 3px #0b0d121a; }
.btn { display: inline-flex; align-items: center; height: 40px; padding: 0 16px; border-radius: 11px; font-size: 14px; font-weight: 600; text-decoration: none; }
.btn--ink { background: var(--vb-ink); color: #fff; }
.btn--ink:hover { background: var(--vb-ink-3); }

/* Shared type */
.eyebrow { display: inline-flex; align-items: center; gap: 8px; margin: 0 0 18px; font: 500 13px/1 var(--vb-mono); letter-spacing: .02em; text-transform: uppercase; color: var(--vb-brand); }
.eyebrow--light { color: #8fb0ff; }
.live { position: relative; width: 8px; height: 8px; border-radius: 50%; background: var(--vb-danger); }
.live::after { content: ''; position: absolute; inset: 0; border-radius: 50%; background: var(--vb-danger); animation: live 1.8s ease-out infinite; }
@keyframes live { to { transform: scale(3); opacity: 0; } }
h1, h2, h3 { margin: 0; font-weight: 650; letter-spacing: -.045em; }
h2 { font-size: clamp(36px, 5.4vw, 64px); line-height: .98; }
h3 { font-size: 21px; letter-spacing: -.025em; }
.pre { white-space: pre-line; }
.lead { max-width: 34em; margin: 22px 0 32px; font-size: clamp(17px, 1.5vw, 19px); line-height: 1.55; color: var(--vb-muted); }
.lead--light { color: #a9aebb; }

/* Hero */
.hero { position: relative; padding: 40px 0 72px; overflow: clip; }
.hero__rings { position: absolute; top: 50%; right: -260px; width: 1100px; height: 1100px; transform: translateY(-50%); pointer-events: none; }
.hero__rings circle { fill: none; stroke: var(--vb-line); stroke-width: 1; }
.hero__grid { position: relative; display: grid; gap: 56px; align-items: center; }
.hero h1 { font-size: clamp(72px, 13vw, 168px); line-height: .86; letter-spacing: -.065em; }
.hero__price { margin: 18px 0 0; font-size: 14px; color: var(--vb-muted); }
.hero__visual { position: relative; display: flex; justify-content: center; }
.hero__phone { --phone-w: min(320px, 78vw); }
.float { position: absolute; display: inline-flex; align-items: center; gap: 8px; padding: 10px 14px; border-radius: 14px; background: #fff; box-shadow: var(--vb-shadow); font-size: 14px; font-weight: 600; white-space: nowrap; animation: float 6s ease-in-out infinite; }
.float--a { top: 24%; left: max(0px, calc(50% - 250px)); }
.float--b { bottom: 14%; right: max(0px, calc(50% - 250px)); animation-delay: -3s; }
.tone { width: 9px; height: 9px; border-radius: 50%; }
.tone--danger { background: var(--vb-danger); box-shadow: 0 0 0 4px #e5484d22; }
.tone--info { background: var(--vb-info); box-shadow: 0 0 0 4px #2f6bff22; }
@keyframes float { 50% { transform: translateY(-8px); } }
@media (max-width: 520px) { .float { display: none; } }
@media (min-width: 980px) {
  .hero { padding: 56px 0 104px; }
  .hero__grid { grid-template-columns: 1.1fr .9fr; }
  .hero__rings { right: -380px; }
  .float--a { left: -10px; }
  .float--b { right: -30px; }
}

/* Sources */
.sources { border-block: 1px solid var(--vb-line); background: #fff; }
.sources__inner { display: grid; gap: 14px; align-items: center; padding: 22px 0; }
.sources__label { margin: 0; font: 500 13px var(--vb-mono); text-transform: uppercase; color: var(--vb-muted); }
.sources__label b { color: var(--vb-ink); }
.sources__users { margin: 0; font-size: 14px; color: var(--vb-muted); }
.marquee { overflow: hidden; mask-image: linear-gradient(90deg, transparent, #000 8%, #000 92%, transparent); }
.marquee__track { display: flex; gap: 40px; width: max-content; animation: marquee 40s linear infinite; }
.marquee__track span { font-size: 20px; font-weight: 620; letter-spacing: -.03em; color: var(--vb-ink-3); white-space: nowrap; }
@keyframes marquee { to { transform: translateX(calc(-50% - 20px)); } }
@media (min-width: 980px) { .sources__inner { grid-template-columns: auto 1fr auto; gap: 32px; } }

/* Sections */
.section { padding: 96px 0; }
@media (min-width: 980px) { .section { padding: 136px 0; } }
.section__head { max-width: 760px; margin-bottom: 56px; }
.section--ink { background: var(--vb-ink); color: #fff; }
.section--paper2 { background: #fff; }

.steps { display: grid; gap: 64px; margin: 0; padding: 0; list-style: none; }
.step__stage { display: flex; justify-content: center; padding: 36px 0; margin-bottom: 26px; border-radius: var(--vb-radius-lg); background: linear-gradient(180deg, #fff, #eef0f3); }
.step__phone { --phone-w: 240px; }
.step__num { margin: 0 0 8px; font: 500 13px var(--vb-mono); color: var(--vb-brand); }
.step p:last-child { margin: 10px 0 0; max-width: 28em; font-size: 16px; line-height: 1.55; color: var(--vb-muted); }
@media (min-width: 900px) { .steps { grid-template-columns: repeat(3, 1fr); gap: 28px; } .step__phone { --phone-w: min(240px, 19vw); } }

.split { display: grid; gap: 56px; align-items: center; }
.split__visual { display: flex; justify-content: center; }
.split__visual .phone { --phone-w: min(300px, 76vw); }
@media (min-width: 980px) {
  .split { grid-template-columns: 1fr 1fr; gap: 96px; }
  .split--flip .split__visual { order: -1; }
}

.levels { display: grid; gap: 4px; margin: 0; padding: 0; list-style: none; }
.levels li { display: flex; gap: 16px; padding: 18px 0; border-top: 1px solid #ffffff17; }
.levels b { display: block; margin-bottom: 4px; font-size: 17px; font-weight: 620; }
.levels span { color: #a9aebb; font-size: 15px; line-height: 1.5; }
.level { flex: 0 0 auto; width: 12px; height: 12px; margin-top: 6px; border-radius: 50%; }
.level--danger { background: var(--vb-danger); box-shadow: 0 0 0 5px #e5484d33; }
.level--warn { background: var(--vb-warn); }
.level--info { background: var(--vb-info); }

.checks { display: grid; gap: 14px; margin: 0; padding: 0; list-style: none; }
.checks li { display: flex; align-items: center; gap: 12px; font-size: 17px; font-weight: 520; }
.checks svg { padding: 4px; width: 26px; height: 26px; border-radius: 50%; color: #fff; background: var(--vb-brand); }

/* AI */
.endpoint { display: grid; gap: 1px; margin: 0; overflow: hidden; border-radius: var(--vb-radius); background: var(--vb-line); box-shadow: inset 0 0 0 1px var(--vb-line); }
.endpoint div { display: grid; gap: 4px; padding: 14px 18px; background: var(--vb-paper); }
.endpoint dt { font: 500 11px var(--vb-mono); text-transform: uppercase; color: var(--vb-muted); }
.endpoint dd { margin: 0; font: 500 14px var(--vb-mono); overflow-wrap: anywhere; }
.chat { width: min(460px, 100%); border-radius: 22px; background: var(--vb-paper); box-shadow: var(--vb-shadow-lg); overflow: hidden; }
.chat__bar { display: flex; align-items: center; gap: 6px; margin: 0; padding: 14px 18px; border-bottom: 1px solid var(--vb-line); background: #fff; font-size: 13px; font-weight: 600; color: var(--vb-muted); }
.chat__bar i { width: 10px; height: 10px; border-radius: 50%; background: var(--vb-line); }
.chat__bar span { margin-left: 8px; }
.chat__body { display: grid; gap: 14px; padding: 22px 18px 26px; }
.bubble { margin: 0; padding: 14px 16px; border-radius: 18px; font-size: 15px; line-height: 1.5; }
.is-playing .bubble, .is-playing .tool { animation: chat 10s var(--vb-ease) infinite; }
.bubble--me { justify-self: end; max-width: 82%; border-bottom-right-radius: 6px; background: var(--vb-ink); color: #fff; }
.bubble--ai { max-width: 92%; border-bottom-left-radius: 6px; background: #fff; box-shadow: 0 1px 2px #0b0d120f; }
.is-playing .bubble--ai { animation-delay: 2.2s; }
.is-playing .tool { animation-delay: 1.1s; }
.tool { display: inline-flex; align-items: center; gap: 8px; justify-self: start; margin: 0; padding: 7px 12px 7px 8px; border-radius: 99px; background: #fff; box-shadow: inset 0 0 0 1px var(--vb-line); font: 500 12px var(--vb-mono); color: var(--vb-muted); }
.tool :deep(svg) { border-radius: 5px; }
.tool__ok { color: var(--vb-ok); font-weight: 700; }
@keyframes chat { 0% { opacity: 0; transform: translateY(8px); } 6%, 88% { opacity: 1; transform: none; } 96%, 100% { opacity: 0; } }
/* Voor het eerste frame van een vertraagde animatie niet zichtbaar flitsen. */
.is-playing .bubble--ai, .is-playing .tool { animation-fill-mode: backwards; }

/* Trust */
.trust { padding: 96px 0; }
.trust__grid { display: grid; gap: 14px; }
.trust article { padding: 28px; border-radius: var(--vb-radius-lg); background: #fff; }
.trust h3 { margin: 18px 0 8px; font-size: 18px; }
.trust p { margin: 0; color: var(--vb-muted); font-size: 15px; line-height: 1.55; }
.trust__icon { display: grid; place-items: center; width: 44px; height: 44px; border-radius: 13px; background: var(--vb-brand-50); color: var(--vb-brand); }
.trust__icon--sos { background: #fde4e5; color: var(--vb-danger); font-size: 14px; letter-spacing: -.02em; }
@media (min-width: 900px) { .trust__grid { grid-template-columns: repeat(3, 1fr); } }

/* Price */
.price-section { padding-top: 0; }
.price { position: relative; display: grid; gap: 40px; padding: clamp(32px, 6vw, 72px); overflow: hidden; border-radius: 32px; background: var(--vb-ink); color: #fff; scroll-margin-top: 90px; }
.price__amount { display: flex; align-items: baseline; gap: 12px; margin: 0 0 10px; font-size: clamp(64px, 10vw, 120px); font-weight: 680; letter-spacing: -.06em; line-height: 1; }
.price__amount span { font-size: 18px; font-weight: 500; letter-spacing: 0; color: #a9aebb; }
.price h2 { margin-bottom: 32px; font-size: clamp(26px, 3.2vw, 38px); }
.price__note { margin: 16px 0 0; font-size: 14px; color: #a9aebb; }
.price__list { position: relative; z-index: 1; display: grid; align-content: center; gap: 16px; margin: 0; padding: 0; list-style: none; }
.price__list li { display: flex; align-items: center; gap: 12px; font-size: 18px; font-weight: 520; }
.price__list svg { color: #8fb0ff; }
.price__left { position: relative; z-index: 1; }
.price__mark { position: absolute; right: -90px; bottom: -110px; opacity: .06; }
@media (min-width: 980px) { .price { grid-template-columns: 1.2fr .8fr; } }

/* Footer */
.footer { padding: 48px 0 56px; border-top: 1px solid var(--vb-line); }
.footer__inner { display: grid; gap: 24px; align-items: start; }
.footer__motto { margin: 10px 0 0; font-size: 14px; color: var(--vb-muted); }
.footer__sos { margin: 0; font-size: 14px; font-weight: 600; }
.footer__links { display: flex; flex-wrap: wrap; gap: 8px 22px; font-size: 14px; color: var(--vb-muted); }
.footer__links a { color: inherit; text-decoration: none; }
.footer__links a:hover { color: var(--vb-ink); }
@media (min-width: 900px) { .footer__inner { grid-template-columns: 1fr auto auto; gap: 48px; } }

a:focus-visible, button:focus-visible { outline: 3px solid var(--vb-brand); outline-offset: 3px; border-radius: 8px; }

@media (prefers-reduced-motion: reduce) {
  .marquee__track, .float, .live::after { animation: none; }
}
</style>
