<template>
  <div ref="siteEl" class="site">
    <header class="nav" :class="{ 'nav--scrolled': scrolled }">
      <div class="wrap nav__inner">
        <a href="#top" class="nav__logo" aria-label="VeiligerBuiten"><Logo :size="30" /></a>
        <nav class="nav__links" aria-label="Secties">
          <a href="#how">{{ t.nav.how }}</a>
          <a href="#women">{{ t.nav.women }}</a>
          <a href="#sources">{{ t.nav.sources }}</a>
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
        <div class="hero__backdrop" aria-hidden="true"><img src="/images/amsterdam-dusk.webp" alt="" fetchpriority="high" width="2056" height="765" /></div>
        <svg class="hero__rings" viewBox="0 0 800 800" aria-hidden="true">
          <circle v-for="r in [120, 200, 280, 360]" :key="r" cx="400" cy="400" :r="r" />
        </svg>
        <div class="wrap hero__grid">
          <div class="hero__copy">
            <p class="eyebrow"><span class="live" />{{ t.hero.eyebrow }}</p>
            <h1>Be <span>safe.</span><i aria-hidden="true">↗</i></h1>
            <p class="hero__caption">{{ t.hero.caption }}</p>
            <p class="lead">{{ t.hero.lead }}</p>
            <StoreButtons :copy="t" />
            <p class="hero__price">{{ t.hero.price }}</p>
            <div class="hero__community">
              <div class="avatars" aria-hidden="true"><span>↗</span><span>◎</span><span>✳</span></div>
              <div><p><strong>{{ formattedUsers }}</strong> {{ t.hero.users }}</p><small>{{ t.hero.updated }}</small></div>
            </div>
          </div>
          <div class="hero__visual">
            <div class="hero__orbit" aria-hidden="true"><span /><span /><span /></div>
            <PhoneFrame class="hero__phone"><MapScene :s="t.scene" /></PhoneFrame>
            <span class="float float--a"><i class="tone tone--danger" />{{ t.hero.chipConfirmed }}</span>
            <span class="float float--b"><i class="tone tone--info" />{{ t.hero.chipSource }}</span>
            <span class="hero__example">{{ t.hero.example }}</span>
          </div>
        </div>
        <div class="wrap hero__bottom"><a href="#how">{{ t.hero.explore }} <span aria-hidden="true">↓</span></a><span><i />{{ t.hero.location }}</span></div>
      </section>

      <!-- Bronnen -->
      <section class="sources" aria-label="Bronnen">
        <div class="wrap sources__inner">
          <p class="sources__label"><b>{{ sourceCount }}</b> {{ t.sources.label }}</p>
          <div class="sources__tags"><span v-for="tag in t.sources.tags" :key="tag">{{ tag }}</span></div>
        </div>
      </section>

      <!-- Hoe het werkt -->
      <section id="how" class="section">
        <div class="wrap">
          <header class="section__head">
            <p class="eyebrow"><span class="section-index">01 /</span>{{ t.how.eyebrow }}</p>
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

      <AlertExamples :copy="t.examples" />

      <!-- Voor vrouwen: intimidatie op straat -->
      <WomenSafety :copy="t.women" />

      <section id="sources" class="section intelligence">
        <div class="wrap">
          <div class="split intelligence__top">
            <div class="split__copy">
              <p class="eyebrow"><span class="section-index">02 /</span>{{ t.sources.eyebrow }}</p>
              <h2 class="pre">{{ t.sources.title }}</h2>
              <p class="lead">{{ t.sources.lead }}</p>
              <div class="community-note"><span aria-hidden="true">↗</span><div><h3>{{ t.sources.community }}</h3><p>{{ t.sources.communityText }}</p></div></div>
            </div>
            <div class="signal-map" role="img" :aria-label="`${t.sources.official} + ${t.sources.people}: ${t.sources.hub}`">
              <div class="signal-map__grid" />
              <div class="signal-map__ring signal-map__ring--outer" /><div class="signal-map__ring signal-map__ring--inner" />
              <svg class="signal-map__paths" viewBox="0 0 500 440" fill="none" aria-hidden="true"><path d="M90 110H170Q250 110 250 220M410 110H330Q250 110 250 220M80 330H165Q250 330 250 220M420 330H335Q250 330 250 220" /></svg>
              <div class="signal-node signal-node--a"><span class="signal-node__icon">{{ sourceCount }}</span><span>{{ t.sources.official }}</span><i /></div>
              <div class="signal-node signal-node--b"><span class="signal-node__icon">↗</span>{{ t.sources.tags[1] }}</div>
              <div class="signal-node signal-node--c"><span class="signal-node__icon">☀</span>{{ t.sources.tags[2] }}</div>
              <div class="signal-node signal-node--d"><span class="signal-node__icon">◎</span>{{ t.sources.people }}<i /></div>
              <div class="signal-map__hub"><LogoMark :size="74" /><span>{{ t.sources.hub }}</span></div>
              <span class="signal-dot signal-dot--a" /><span class="signal-dot signal-dot--b" /><span class="signal-dot signal-dot--c" />
            </div>
          </div>
          <div class="sync-intro"><h3>{{ t.sources.syncTitle }}</h3><p>{{ t.sources.syncLead }}</p></div>
          <ol class="sync-steps"><li v-for="(step, index) in t.sources.steps" :key="step.title"><span class="sync-steps__number">0{{ index + 1 }}<span aria-hidden="true">{{ ['↗', '⟳', '✓'][index] }}</span></span><h3>{{ step.title }}</h3><p>{{ step.text }}</p></li></ol>
        </div>
      </section>

      <OfflineSyncStory :copy="t.offline" />

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
            <div class="ai-connections"><span>ChatGPT <i>↗</i></span><span>Claude <i>↗</i></span><span>{{ t.ai.chat.assistant }} <i>↗</i></span></div>
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
              <NuxtLink to="/aanmelden" class="price__cta">{{ t.price.cta }}</NuxtLink>
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
          <a href="/support">{{ t.footer.help }}</a>
          <a href="/account-verwijderen">{{ t.footer.remove }}</a>
          <a href="mailto:hallo@veiligerbuiten.nl">{{ t.footer.contact }}</a>
          <span>© {{ year }} VeiligerBuiten</span>
        </nav>
      </div>
    </footer>
  </div>
</template>

<script setup lang="ts">
import { siteCopy } from '~/locales/site';

definePageMeta({ layout: false });

const { locale, setLocale } = useLocale();
const t = computed(() => siteCopy[locale.value]);
/**
 * Bronnen die standaard draaien: P2000 via Zwaailicht, Sirenes, Alarmeringen.nl en de P2000-monitor,
 * plus Politie, NL-Alert, NDW en KNMI-aardbevingen. Houd dit eerlijk.
 */
const sourceCount = 9;
const siteEl = ref<HTMLElement | null>(null);
const { data: community } = useFetch<{ total: number; updatedAt: string }>('/api/site/community', { server: false, lazy: true, retry: 0 });
const displayedUsers = ref<number | null>(null);
const formattedUsers = computed(() => displayedUsers.value === null ? '—' : new Intl.NumberFormat(locale.value === 'nl' ? 'nl-NL' : 'en-GB').format(displayedUsers.value));
let animateCount: ((total: number) => void) | undefined;
watch(() => community.value?.total, (total) => {
  if (!Number.isSafeInteger(total) || total! < 976) return;
  if (animateCount) animateCount(total!);
  else displayedUsers.value = total!;
}, { immediate: true });
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
let motion: { revert: () => void } | undefined;
let disposed = false;
onMounted(async () => {
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });
  const [{ gsap }, { ScrollTrigger }] = await Promise.all([import('gsap'), import('gsap/ScrollTrigger')]);
  if (disposed || !siteEl.value) return;
  gsap.registerPlugin(ScrollTrigger);
  const media = gsap.matchMedia();
  motion = media;
  media.add('(prefers-reduced-motion: no-preference)', () => {
    gsap.from('.hero__copy > :not(.hero__community)', { y: 26, opacity: 0, duration: .9, stagger: .09, ease: 'power3.out', clearProps: 'all' });
    gsap.from('.hero__visual', { y: 50, rotation: 4, opacity: 0, duration: 1.4, ease: 'power3.out', clearProps: 'all' });
    gsap.from('.hero__community, .hero__bottom', { opacity: 0, y: 12, delay: .6, duration: 1, clearProps: 'all' });
    gsap.to('.hero__backdrop img', { yPercent: 12, ease: 'none', scrollTrigger: { trigger: '.hero', start: 'top top', end: 'bottom top', scrub: 1 } });
    gsap.utils.toArray<HTMLElement>('.section__head, .women-copy, .women-sheet, .women-moments li, .women-tips, .women-bystander, .women-faq, .women-help, .step, .split__copy, .split__visual, .signal-map, .sync-intro, .sync-steps li, .trust article, .price, .offline-story, .alert-examples').forEach((element) => {
      gsap.from(element, { y: 36, opacity: 0, duration: .85, ease: 'power2.out', clearProps: 'all', scrollTrigger: { trigger: element, start: 'top 92%', once: true } });
    });
    gsap.from('.signal-map__paths path', { strokeDashoffset: 420, duration: 2.4, ease: 'power2.inOut', scrollTrigger: { trigger: '.signal-map', start: 'top 80%', once: true } });
    let countTween: ReturnType<typeof gsap.to> | undefined;
    animateCount = (total) => {
      countTween?.kill();
      const counter = { value: Math.max(976, total - 36) };
      countTween = gsap.to(counter, { value: total, duration: 1.6, ease: 'power2.out', onUpdate: () => { displayedUsers.value = Math.round(counter.value); } });
    };
    if (community.value?.total) animateCount(community.value.total);
    return () => { countTween?.kill(); animateCount = undefined; if (community.value) displayedUsers.value = community.value.total; };
  }, siteEl.value);
});
onBeforeUnmount(() => { disposed = true; motion?.revert(); window.removeEventListener('scroll', onScroll); });
</script>

<style scoped>
.site { min-height: 100vh; color: var(--vb-ink); overflow-x: clip; }
.wrap { margin: 0 auto; }

/* Nav */
.nav { position: sticky; top: 0; z-index: 50; transition: background .25s, box-shadow .25s; }
.nav--scrolled { background: #f4f5f7d9; backdrop-filter: saturate(1.4) blur(16px); -webkit-backdrop-filter: saturate(1.4) blur(16px); box-shadow: 0 1px 0 var(--vb-line); }
.nav__inner { display: flex; align-items: center; gap: 24px; }
.nav__logo { text-decoration: none; }
.nav__links { display: none; margin: 0 auto; }
.nav__links a { color: var(--vb-muted); font-weight: 500; text-decoration: none; transition: color .15s; }
.nav__links a:hover { color: var(--vb-ink); }
.nav__end { display: flex; align-items: center; gap: 10px; margin-left: auto; }
@media (min-width: 900px) { .nav__links { display: flex; } .nav__end { margin-left: 0; } }
.lang { display: inline-flex; padding: 3px; border: 0; font: 600 12px var(--vb-mono); }
.lang span { padding: 6px 8px; color: var(--vb-subtle); }
.lang span.on { background: #fff; color: var(--vb-ink); box-shadow: 0 1px 3px #0b0d121a; }
.btn { display: inline-flex; align-items: center; height: 40px; padding: 0 16px; border-radius: 11px; font-size: 14px; font-weight: 600; text-decoration: none; }
.btn--ink { background: var(--vb-ink); color: #fff; }
.btn--ink:hover { background: var(--vb-ink-3); }

/* Shared type */
.eyebrow { display: inline-flex; align-items: center; margin: 0 0 18px; font: 500 13px/1 var(--vb-mono); text-transform: uppercase; color: var(--vb-brand); }
.eyebrow--light { color: #8fb0ff; }
.live { position: relative; width: 8px; height: 8px; border-radius: 50%; background: var(--vb-danger); }
.live::after { content: ''; position: absolute; inset: 0; border-radius: 50%; background: var(--vb-danger); animation: live 1.8s ease-out infinite; }
@keyframes live { to { transform: scale(3); opacity: 0; } }
h1, h2, h3 { margin: 0; font-weight: 650; letter-spacing: -.045em; }
h3 { font-size: 21px; letter-spacing: -.025em; }
.pre { white-space: pre-line; }
.lead { max-width: 34em; margin: 22px 0 32px; font-size: clamp(17px, 1.5vw, 19px); line-height: 1.55; color: var(--vb-muted); }
.lead--light { color: #a9aebb; }

/* Hero */
.hero { position: relative; overflow: clip; }
.hero__rings { position: absolute; top: 50%; width: 1100px; height: 1100px; transform: translateY(-50%); pointer-events: none; }
.hero__rings circle { fill: none; }
.hero__grid { position: relative; display: grid; align-items: center; }
.hero__price { margin: 18px 0 0; }
.hero__visual { position: relative; display: flex; justify-content: center; }
.float { position: absolute; display: inline-flex; align-items: center; gap: 8px; font-weight: 600; white-space: nowrap; animation: float 6s ease-in-out infinite; }
.float--b { animation-delay: -3s; }
.tone { width: 9px; height: 9px; border-radius: 50%; }
.tone--danger { background: var(--vb-danger); box-shadow: 0 0 0 4px #e5484d22; }
.tone--info { background: var(--vb-info); box-shadow: 0 0 0 4px #2f6bff22; }
@keyframes float { 50% { transform: translateY(-8px); } }
@media (max-width: 520px) { .float { display: none; } }

/* Sources */
.sources { border-block: 1px solid var(--vb-line); }
.sources__inner { align-items: center; }
.sources__label { margin: 0; color: var(--vb-muted); }
.sources__label b { color: var(--vb-ink); }
@keyframes marquee { to { transform: translateX(calc(-50% - 20px)); } }
@media (min-width: 980px) { .sources__inner { grid-template-columns: auto 1fr auto; } }

/* Sections */
.section__head { max-width: 760px; }
.section--ink { color: #fff; }

.steps { display: grid; margin: 0; padding: 0; list-style: none; }
.step__stage { display: flex; justify-content: center; }
.step__num { margin: 0 0 8px; font: 500 13px var(--vb-mono); color: var(--vb-brand); }
.step p:last-child { margin: 10px 0 0; max-width: 28em; color: var(--vb-muted); }
@media (min-width: 900px) { .steps { grid-template-columns: repeat(3, 1fr); } }

.split { display: grid; gap: 56px; align-items: center; }
.split__visual { display: flex; justify-content: center; }
.split__visual .phone { --phone-w: min(300px, 76vw); }
@media (min-width: 980px) {
  .split { grid-template-columns: 1fr 1fr; gap: 96px; }
  .split--flip .split__visual { order: -1; }
}

.levels { display: grid; gap: 4px; margin: 0; padding: 0; list-style: none; }
.levels li { display: flex; gap: 16px; padding: 18px 0; border-top: 1px solid #ffffff17; }
.levels b { display: block; margin-bottom: 4px; font-size: 17px; }
.levels span { color: #a9aebb; line-height: 1.5; }
.level { flex: 0 0 auto; width: 12px; height: 12px; margin-top: 6px; border-radius: 50%; }
.level--danger { background: var(--vb-danger); box-shadow: 0 0 0 5px #e5484d33; }
.level--warn { background: var(--vb-warn); }
.level--info { background: var(--vb-info); }

.checks { display: grid; gap: 14px; margin: 0; padding: 0; list-style: none; }
.checks li { display: flex; align-items: center; }
.checks svg { padding: 4px; border-radius: 50%; }

/* AI */
.chat { width: min(460px, 100%); background: var(--vb-paper); overflow: hidden; }
.chat__bar { display: flex; align-items: center; gap: 6px; margin: 0; border-bottom: 1px solid var(--vb-line); background: #fff; font-size: 13px; font-weight: 600; color: var(--vb-muted); }
.chat__bar i { width: 10px; height: 10px; border-radius: 50%; background: var(--vb-line); }
.chat__bar span { margin-left: 8px; }
.chat__body { display: grid; }
.bubble { margin: 0; padding: 14px 16px; border-radius: 18px; line-height: 1.5; }
.is-playing .bubble, .is-playing .tool { animation: chat 10s var(--vb-ease) infinite; }
.bubble--me { justify-self: end; max-width: 82%; border-bottom-right-radius: 6px; color: #fff; }
.bubble--ai { max-width: 92%; border-bottom-left-radius: 6px; background: #fff; }
.is-playing .bubble--ai { animation-delay: 2.2s; }
.is-playing .tool { animation-delay: 1.1s; }
.tool { display: inline-flex; align-items: center; gap: 8px; justify-self: start; margin: 0; padding: 7px 12px 7px 8px; border-radius: 99px; background: #fff; box-shadow: inset 0 0 0 1px var(--vb-line); font: 500 12px var(--vb-mono); color: var(--vb-muted); }
.tool :deep(svg) { border-radius: 5px; }
.tool__ok { color: var(--vb-ok); font-weight: 700; }
@keyframes chat { 0% { opacity: 0; transform: translateY(8px); } 6%, 88% { opacity: 1; transform: none; } 96%, 100% { opacity: 0; } }
/* Voor het eerste frame van een vertraagde animatie niet zichtbaar flitsen. */
.is-playing .bubble--ai, .is-playing .tool { animation-fill-mode: backwards; }

/* Trust */
.trust__grid { display: grid; gap: 14px; }
.trust h3 { margin: 18px 0 8px; }
.trust p { margin: 0; color: var(--vb-muted); }
.trust__icon { display: grid; place-items: center; width: 44px; height: 44px; border-radius: 13px; background: var(--vb-brand-50); color: var(--vb-brand); }
.trust__icon--sos { background: #fde4e5; color: var(--vb-danger); font-size: 14px; letter-spacing: -.02em; }
@media (min-width: 900px) { .trust__grid { grid-template-columns: repeat(3, 1fr); } }

/* Price */
.price { position: relative; display: grid; gap: 40px; padding: clamp(32px, 6vw, 72px); overflow: hidden; color: #fff; scroll-margin-top: 90px; }
.price__amount { display: flex; align-items: baseline; gap: 12px; margin: 0 0 10px; font-size: clamp(64px, 10vw, 120px); letter-spacing: -.06em; line-height: 1; }
.price__amount span { font-size: 18px; font-weight: 500; letter-spacing: 0; color: #a9aebb; }
.price h2 { margin-bottom: 32px; font-size: clamp(26px, 3.2vw, 38px); }
.price__cta { display: inline-flex; align-items: center; justify-content: center; height: 54px; margin: 0 0 14px; padding: 0 26px; border-radius: 14px; color: #fff; background: var(--vb-brand); font-size: 17px; font-weight: 620; text-decoration: none; transition: transform .2s var(--vb-ease); }
.price__cta:hover { transform: translateY(-2px); }
.price__note { margin: 16px 0 0; font-size: 14px; color: #a9aebb; }
.price__list { position: relative; z-index: 1; display: grid; align-content: center; gap: 16px; margin: 0; padding: 0; list-style: none; }
.price__list li { display: flex; align-items: center; gap: 12px; }
.price__list svg { color: #8fb0ff; }
.price__left { position: relative; z-index: 1; }
.price__mark { position: absolute; right: -90px; bottom: -110px; opacity: .06; }
@media (min-width: 980px) { .price { grid-template-columns: 1.2fr .8fr; } }

/* Footer */
.footer { border-top: 1px solid var(--vb-line); }
.footer__inner { display: grid; align-items: start; }
.footer__motto { margin: 10px 0 0; color: var(--vb-muted); }
.footer__sos { margin: 0; }
.footer__links { display: flex; flex-wrap: wrap; gap: 8px 22px; color: var(--vb-muted); }
.footer__links a { color: inherit; text-decoration: none; }
.footer__links a:hover { color: var(--vb-ink); }
@media (min-width: 900px) { .footer__inner { grid-template-columns: 1fr auto auto; } }

a:focus-visible, button:focus-visible { outline: 3px solid var(--vb-brand); outline-offset: 3px; border-radius: 8px; }

/* Landing page visual system. */
.site { background: #f9fafb; --gutter: 24px; }
.wrap { width: min(1200px, 100% - var(--gutter) * 2); }
.nav { background: #f9fafbf2; }
.nav__inner { height: 86px; }
.nav__logo :deep(svg) { height: 32px; }
.nav__links { gap: 25px; }
.nav__links a { font-size: 13px; }
.nav__end .btn { height: 43px; border-radius: 99px; padding-inline: 22px; }
.lang { background: #edf0f4; border-radius: 99px; }
.lang span { border-radius: 99px; }
.hero { width: calc(100% - 40px); max-width: 1560px; margin: 0 auto; padding: 64px 0 24px; border-radius: 30px; background: #e9eff5; isolation: isolate; }
.hero__backdrop { position: absolute; inset: 0; z-index: -2; overflow: hidden; }
.hero__backdrop img { width: 100%; height: 115%; object-fit: cover; object-position: 65% 50%; }
.hero__backdrop::after { content: ''; position: absolute; inset: 0; background: linear-gradient(90deg, #eff3f8fa 0%, #eff3f8f5 26%, #eff3f8d9 43%, #e8eff790 65%, #d9e5f330 100%), linear-gradient(0deg, #eaf0f7ed 0%, transparent 30%); }
.hero__rings { opacity: .22; right: -280px; z-index: -1; }
.hero__rings circle { stroke: #fff; stroke-width: 1.5; }
.hero__grid { gap: 32px; grid-template-columns: 1.12fr .88fr; min-height: 620px; }
.hero__copy { padding-bottom: 12px; }
.hero .eyebrow { margin-bottom: 32px; padding: 10px 12px; border: 1px solid #ffffffb8; border-radius: 99px; background: #ffffff80; color: #394c69; font-size: 10px; letter-spacing: .045em; backdrop-filter: blur(8px); }
.hero .live { background: #2f6bff; width: 6px; height: 6px; }
.hero .live::after { background: #2f6bff; }
.hero h1 { position: relative; width: fit-content; font-size: clamp(94px, 10.2vw, 148px); font-weight: 640; letter-spacing: -.08em; line-height: .95; white-space: nowrap; }
.hero h1 > span { color: #2f6bff; }
.hero h1 > i { position: absolute; top: -8px; right: -38px; font-size: 36px; font-style: normal; font-weight: 400; color: #2f6bff; letter-spacing: 0; }
.hero__caption { margin: 26px 0 0; font-size: clamp(18px, 1.6vw, 22px); letter-spacing: -.04em; font-weight: 550; }
.hero .lead { max-width: 430px; font-size: 16px; line-height: 1.7; color: #586576; margin: 14px 0 27px; }
.hero__price { font-size: 12px; color: #566477; margin-top: 14px; }
.hero__community { display: flex; align-items: center; gap: 13px; margin-top: 36px; }
.hero__community p { margin: 0 0 2px; font-size: 12px; color: #455267; }
.hero__community strong { color: #172439; font-size: 20px; font-weight: 650; font-variant-numeric: tabular-nums; letter-spacing: -.04em; margin-right: 5px; }
.hero__community small { font-size: 10px; color: #627086; }
.avatars { display: flex; padding-right: 0; }
.avatars span { display: grid; place-items: center; width: 36px; height: 36px; border: 2px solid #eef3f8; border-radius: 50%; font-size: 19px; font-weight: 500; margin-right: -9px; }
.avatars span:nth-child(1) { background: #cbdafa; color: #2754a1; }
.avatars span:nth-child(2) { background: #dbe6df; color: #40705b; }
.avatars span:nth-child(3) { background: #e7d8cc; color: #84624c; margin-right: 0; }
.hero__visual { perspective: 1000px; padding: 0 0 16px; }
.hero__phone { --phone-w: 276px; transform: rotate(5deg); box-shadow: 15px 30px 65px #152b4b40, 0 0 0 1px #ffffff40; }
.hero__orbit { position: absolute; top: 50%; left: 50%; width: 450px; aspect-ratio: 1; transform: translate(-50%,-50%); z-index: -1; border-radius: 50%; border: 1px solid #ffffff90; background: radial-gradient(circle, #e6efff80, transparent 70%); }
.hero__orbit > span { position: absolute; width: 9px; height: 9px; border: 2px solid #fff; border-radius: 50%; background: #2f6bff; box-shadow: 0 0 0 7px #ffffff30; }
.hero__orbit > span:nth-child(1) { left: 7%; top: 22%; }
.hero__orbit > span:nth-child(2) { right: 2%; top: 65%; background: #ff8a00; }
.hero__orbit > span:nth-child(3) { left: 24%; bottom: 5%; background: #1f9d55; }
.float { border: 1px solid #ffffffc9; padding: 13px 17px; font-size: 12px; background: #fffffff2; backdrop-filter: blur(16px); box-shadow: 0 10px 32px #162c4c18; border-radius: 12px; z-index: 2; }
.float--a { left: -5px; top: 20%; transform: rotate(-4deg); }
.float--b { right: -5px; bottom: 19%; }
.hero__example { position: absolute; bottom: -15px; left: 50%; transform: translateX(-50%); color: #33465f; font: 10px var(--vb-mono); white-space: nowrap; letter-spacing: .01em; }
.hero__bottom { position: relative; display: flex; align-items: center; justify-content: space-between; margin-top: 38px; padding-top: 20px; border-top: 1px solid #62708622; font: 10px var(--vb-mono); color: #45566f; }
.hero__bottom a { display: flex; gap: 18px; align-items: center; text-decoration: none; color: inherit; }
.hero__bottom a span { font: 16px var(--vb-font); }
.hero__bottom > span { display: flex; align-items: center; gap: 8px; }
.hero__bottom > span i { width: 5px; height: 5px; border-radius: 50%; background: #2f6bff; }
.sources { margin-top: 28px; border: 0; background: transparent; }
.sources__inner { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 24px; padding: 18px 0 34px; border-bottom: 1px solid #e3e7ed; }
.sources__label { font: 500 12px var(--vb-font); text-transform: none; letter-spacing: -.02em; }
.sources__label b { font-size: 24px; font-weight: 620; margin-right: 5px; }
.sources__tags { display: flex; flex-wrap: wrap; gap: 24px; }
.sources__tags span { font-size: 12px; color: #6c7684; font-weight: 500; display: flex; align-items: center; gap: 8px; }
.sources__tags span::before { content: ''; width: 4px; height: 4px; background: #a7b6ca; border-radius: 50%; }
.section { scroll-margin-top: 85px; padding: 108px 0; }
h2 { font-weight: 570; letter-spacing: -.06em; line-height: 1.04; font-size: clamp(38px, 4.6vw, 62px); }
.eyebrow { font-size: 10px; letter-spacing: .05em; gap: 12px; }
.section-index { color: #8a96a8; }
.section__head { margin-bottom: 42px; }
.steps { gap: 25px; }
.step { padding: 12px 12px 26px; border: 1px solid #e5e9ef; border-radius: 24px; background: #fff; overflow: hidden; transition: border-color .25s, box-shadow .25s; }
.step:hover { border-color: #b8cdf7; box-shadow: 0 12px 36px #21385a09; }
.step__stage { position: relative; height: 365px; overflow: hidden; margin-bottom: 24px; padding: 36px 0 0; border-radius: 15px; background: radial-gradient(ellipse at 50% 90%, #c5d7f2, #eaf0f8 65%); }
.step:nth-child(2) .step__stage { background: radial-gradient(ellipse at 50% 90%, #cbded7, #edf3f0 65%); }
.step:nth-child(3) .step__stage { background: radial-gradient(ellipse at 50% 90%, #dfd3c6, #f4f0ea 65%); }
.step__phone { --phone-w: 215px; transform: rotate(-5deg); }
.step:nth-child(2) .step__phone { transform: rotate(4deg); }
.step:nth-child(3) .step__phone { transform: rotate(-3deg); }
.step__num, .step > h3, .step > p:last-child { margin-left: 14px; margin-right: 14px; }
.step__num { margin-bottom: 10px; font-size: 10px; }
.step h3 { font-size: 21px; font-weight: 570; letter-spacing: -.04em; }
.step p:last-child { font-size: 14px; line-height: 1.65; margin-top: 12px; }
.intelligence { position: relative; background: #edf2f8; overflow: hidden; }
.intelligence::before { content: ''; position: absolute; top: 0; right: -200px; width: 700px; height: 600px; background: radial-gradient(ellipse, #dae6fa88, transparent 70%); pointer-events: none; }
.intelligence__top { gap: 54px; position: relative; }
.intelligence .lead { font-size: 16px; line-height: 1.7; max-width: 450px; }
.community-note { display: flex; gap: 15px; border-top: 1px solid #d5dfeb; padding-top: 23px; max-width: 450px; }
.community-note > span { display: grid; place-items: center; width: 34px; height: 34px; background: #dce7fa; color: #2f6bff; border-radius: 10px; flex: 0 0 auto; font-size: 21px; }
.community-note h3 { font-size: 16px; font-weight: 570; letter-spacing: -.025em; }
.community-note p { font-size: 14px; line-height: 1.65; color: #657185; margin: 8px 0 0; }
.signal-map { position: relative; width: 100%; aspect-ratio: 500 / 440; border: 1px solid #ffffffc9; border-radius: 26px; background: #f8faff99; overflow: hidden; box-shadow: 0 20px 50px #33476705; }
.signal-map__grid { position: absolute; inset: 0; background-image: radial-gradient(#7892b14d .8px, transparent .8px); background-size: 20px 20px; mask-image: radial-gradient(ellipse, #000, transparent 75%); }
.signal-map__ring { position: absolute; top: 50%; left: 50%; border: 1px solid #c6d4e778; border-radius: 50%; transform: translate(-50%,-50%); }
.signal-map__ring--outer { width: 76%; aspect-ratio: 1; }
.signal-map__ring--inner { width: 48%; aspect-ratio: 1; background: #e8f0fc55; }
.signal-map__paths { position: absolute; inset: 0; width: 100%; height: 100%; stroke: #83a8f6; stroke-width: 1.4; stroke-dasharray: 420; stroke-dashoffset: 0; }
.signal-map__hub { position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); display: grid; justify-items: center; gap: 13px; font-size: 11px; font-weight: 550; white-space: nowrap; color: #40526c; }
.signal-map__hub :deep(svg) { box-shadow: 0 10px 30px #2f6bff24; border-radius: 21px; }
.signal-node { position: absolute; display: flex; align-items: center; gap: 9px; padding: 10px 12px; border: 1px solid #e1e7f1; border-radius: 12px; background: #fff; box-shadow: 0 8px 20px #23466d09; font-size: 10px; font-weight: 550; color: #465671; white-space: nowrap; }
.signal-node__icon { display: grid; place-items: center; width: 25px; height: 25px; border-radius: 7px; background: #edf2fd; color: #2f6bff; font-size: 14px; font-weight: 600; }
.signal-node--a { left: 5%; top: 18%; }
.signal-node--a .signal-node__icon { font-size: 10px; }
.signal-node--b { right: 5%; top: 18%; }
.signal-node--c { left: 5%; bottom: 18%; }
.signal-node--d { right: 5%; bottom: 18%; }
.signal-node i { width: 4px; height: 4px; border-radius: 50%; background: #1f9d55; margin-left: 2px; }
.signal-dot { position: absolute; width: 7px; height: 7px; border: 2px solid #fff; border-radius: 50%; box-shadow: 0 0 0 5px #2f6bff12; background: #2f6bff; }
.signal-dot--a { top: 14%; left: 55%; }
.signal-dot--b { bottom: 24%; left: 42%; background: #ff8a00; }
.signal-dot--c { top: 47%; right: 13%; }
.sync-intro { display: flex; align-items: baseline; justify-content: space-between; gap: 20px; margin: 72px 0 28px; padding-top: 32px; border-top: 1px solid #d7e0ec; }
.sync-intro h3 { font-size: 23px; font-weight: 560; letter-spacing: -.04em; }
.sync-intro p { color: #6a7689; font-size: 13px; margin: 0; }
.sync-steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; padding: 0; margin: 0; list-style: none; }
.sync-steps li { padding: 24px; background: #ffffffa8; border: 1px solid #fff; border-radius: 17px; }
.sync-steps__number { display: flex; align-items: center; justify-content: space-between; font: 11px var(--vb-mono); color: #6d7f9b; margin-bottom: 26px; }
.sync-steps__number > span { font: 25px var(--vb-font); color: #2f6bff; }
.sync-steps h3 { font-size: 18px; font-weight: 570; letter-spacing: -.035em; }
.sync-steps p { margin: 10px 0 0; color: #657185; font-size: 14px; line-height: 1.7; }
.section--ink { position: relative; isolation: isolate; overflow: hidden; background: #101722; }
.section--ink::before { content: ''; position: absolute; z-index: -1; right: -10%; top: -20%; width: 75%; height: 140%; background: radial-gradient(ellipse, #25477855, transparent 65%); }
.section--ink .split__visual { position: relative; }
.section--ink .split__visual::before { content: ''; position: absolute; width: 450px; height: 450px; top: 50%; left: 50%; transform: translate(-50%,-50%); border: 1px solid #ffffff0d; border-radius: 50%; box-shadow: 0 0 0 65px #ffffff03, 0 0 0 130px #ffffff02; }
.levels b { font-weight: 550; }
.levels span { font-size: 14px; }
.checks li { font-size: 15px; font-weight: 450; gap: 12px; }
.checks svg { background: #eaf0ff; color: #2f6bff; width: 23px; height: 23px; }
.section--paper2 { background: #eef2f7; }
.ai-connections { display: flex; flex-wrap: wrap; gap: 8px; }
.ai-connections > span { display: flex; align-items: center; gap: 25px; padding: 11px 14px; border: 1px solid #d9e0eb; border-radius: 9px; font-size: 12px; color: #49596f; background: #ffffff70; }
.ai-connections i { font-style: normal; color: #2f6bff; }
.chat { transform: rotate(-2deg); border: 1px solid #dce3ed; border-radius: 19px; box-shadow: 0 24px 60px #1b34531a; }
.chat__bar { padding: 17px 20px; }
.chat__body { padding: 26px 21px 32px; gap: 20px; background: linear-gradient(145deg,#f8faff,#eef3fa); }
.bubble { font-size: 14px; }
.bubble--me { background: #2f6bff; }
.bubble--ai { box-shadow: 0 5px 15px #233e6308; }
.trust { padding: 76px 0; }
.trust article { border: 1px solid #e5e9ef; background: #fff; padding: 28px; border-radius: 18px; }
.trust h3 { font-size: 17px; font-weight: 550; }
.trust p { font-size: 14px; line-height: 1.7; }
.price-section { padding-top: 0; padding-bottom: 80px; }
.price { background: radial-gradient(ellipse at 90% 10%, #244986 0%, transparent 60%), #101722; border-radius: 25px; }
.price h2 { font-weight: 500; }
.price__list li { font-size: 16px; font-weight: 450; }
.price__amount { font-weight: 570; }
.footer { background: #fff; padding: 40px 0; }
.footer__inner { gap: 26px; }
.footer__links { font-size: 12px; max-width: 300px; }
.footer__sos { font-size: 12px; color: #576274; font-weight: 450; }
.footer__motto { font-size: 12px; }
@media (min-width: 1600px) { .hero { padding-top: 80px; } .hero__grid { min-height: 640px; } .hero__phone { --phone-w: 292px; } }
@media (max-width: 1199px) and (min-width: 980px) { .hero__grid { grid-template-columns: 1.1fr .9fr; min-height: 600px; } .hero h1 { font-size: 108px; } .hero__phone { --phone-w: 248px; } .hero .eyebrow { font-size: 9px; } .float--a { left: -12px; } .float--b { right: -16px; } .hero__orbit { width: 340px; } .sources__tags { gap: 15px; } }
@media (max-width: 979px) { .hero { padding: 48px 0 24px; } .hero__grid { grid-template-columns: 1fr 1fr; min-height: 550px; gap: 12px; } .hero h1 { font-size: 88px; } .hero h1 > i { right: -23px; font-size: 23px; top: 0; } .hero .eyebrow { font-size: 8px; gap: 7px; padding: 9px; } .hero__caption { font-size: 17px; } .hero .lead { font-size: 14px; } .hero__phone { --phone-w: 230px; } .hero__orbit { width: 320px; } .float { font-size: 10px; padding: 10px; } .float--a { left: -10px; } .float--b { right: -14px; } .sources__inner { justify-content: center; } .sources__tags { justify-content: center; } .intelligence__top { grid-template-columns: 1fr 1fr; gap: 32px; } .intelligence h2 { font-size: 42px; } .signal-node { font-size: 8px; padding: 7px; gap: 5px; } .signal-node__icon { width: 21px; height: 21px; } .signal-map__hub :deep(svg) { width: 58px; height: 58px; } .signal-map__hub { font-size: 9px; } .sync-intro { align-items: start; flex-direction: column; gap: 10px; } .footer__inner { gap: 22px; } }
@media (max-width: 767px) { .site { --gutter: 22px; } .site :deep(.stores) { gap: 8px; flex-wrap: nowrap; } .site :deep(.store) { min-width: 0; padding: 0 11px; gap: 8px; height: 50px; flex: 1; max-width: 164px; border-radius: 12px; } .site :deep(.store b) { font-size: 15px; white-space: nowrap; } .site :deep(.store small) { font-size: 9px; white-space: nowrap; } .site :deep(.store svg) { width: 20px; flex: 0 0 auto; } .nav__inner { height: 74px; gap: 10px; } .nav__logo :deep(svg) { max-width: 145px; } .nav__end { gap: 6px; } .nav__end .btn { padding-inline: 15px; font-size: 12px; height: 38px; } .lang { font-size: 10px; } .lang span { padding: 5px 6px; } .hero { width: calc(100% - 24px); border-radius: 23px; padding: 36px 0 20px; } .hero__grid { grid-template-columns: 1fr; gap: 42px; min-height: auto; } .hero__backdrop img { object-position: 68% 50%; } .hero__backdrop::after { background: linear-gradient(180deg,#eff3f8fa 0%,#eff3f8f2 30%,#e9f0f7a0 59%,#eaf0f7d9 100%); } .hero .eyebrow { font-size: 8px; margin-bottom: 23px; padding: 9px 10px; } .hero h1 { font-size: clamp(78px,18.5vw,124px); } .hero h1 > i { right: -25px; top: 0; font-size: 25px; } .hero__caption { margin-top: 20px; font-size: clamp(15px,3.7vw,20px); } .hero .lead { max-width: 440px; font-size: 14px; margin: 13px 0 23px; } .hero__price { margin-top: 12px; font-size: 11px; } .hero__community { margin-top: 26px; } .hero__community strong { font-size: 18px; } .hero__community p { font-size: 11px; } .hero__visual { padding-bottom: 14px; margin-bottom: 6px; } .hero__phone { --phone-w: 240px; } .hero__orbit { width: 340px; } .float { display: inline-flex; font-size: 10px; padding: 10px 12px; } .float--a { left: calc(50% - 164px); top: 23%; } .float--b { right: calc(50% - 166px); bottom: 18%; } .hero__example { font-size: 8px; bottom: -9px; } .hero__bottom { margin-top: 25px; padding-top: 17px; font-size: 8px; gap: 10px; } .hero__bottom a { gap: 8px; } .hero__bottom > span { gap: 5px; } .sources { margin-top: 12px; } .sources__inner { padding: 18px 0 24px; gap: 16px; } .sources__tags { gap: 12px 18px; } .sources__tags span { font-size: 10px; gap: 6px; } .section { padding: 72px 0; } .section__head { margin-bottom: 30px; } h2 { font-size: 40px; } .steps { gap: 20px; } .step__stage { height: 310px; } .step__phone { --phone-w: 230px; } .step { padding-bottom: 28px; } .step p:last-child { max-width: 100%; } .intelligence__top { grid-template-columns: 1fr; gap: 35px; } .intelligence h2 { font-size: 42px; } .intelligence .lead { font-size: 15px; margin-bottom: 24px; } .signal-map { max-width: 500px; margin: 0 auto; } .signal-node { font-size: 9px; padding: 8px; gap: 7px; } .signal-map__hub :deep(svg) { width: 62px; height: 62px; } .sync-intro { margin-top: 40px; padding-top: 26px; } .sync-intro h3 { font-size: 23px; max-width: 270px; } .sync-intro p { font-size: 13px; line-height: 1.6; } .sync-steps { grid-template-columns: 1fr; gap: 12px; } .sync-steps li { padding: 22px; } .sync-steps__number { margin-bottom: 14px; } .sync-steps h3 { font-size: 18px; } .split { gap: 42px; } .lead { font-size: 16px; } .section--ink .split__visual .phone { --phone-w: 260px; } .trust { padding: 50px 0; } .trust__grid { gap: 12px; } .price-section { padding-top: 0; padding-bottom: 48px; } .price { padding: 30px 25px; gap: 28px; } .price__amount { font-size: 74px; gap: 8px; } .price__amount span { font-size: 14px; } .price h2 { font-size: 26px; line-height: 1.1; } .price__list { gap: 13px; } .price__list li { font-size: 14px; } .price__note { font-size: 12px; line-height: 1.5; } .footer__links { max-width: none; } }
@media (max-width: 374px) { .site { --gutter: 17px; } .hero h1 { font-size: 73px; } .hero__caption { font-size: 14px; } .hero .eyebrow { font-size: 7px; } .hero__phone { --phone-w: 215px; } .float--a { left: 0; } .float--b { right: 0; } .nav__logo :deep(svg) { max-width: 127px; } }
@media (prefers-reduced-motion: reduce) { .site *, .site *::before, .site *::after { animation: none !important; transition: none !important; scroll-behavior: auto !important; } }

</style>
