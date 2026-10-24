<template>
  <main class="legal">
    <nav class="legal__nav">
      <NuxtLink to="/" aria-label="VeiligerBuiten"><Logo /></NuxtLink>
      <div class="legal__lang">
        <button :aria-pressed="locale === 'nl'" @click="setLocale('nl')">NL</button>
        <button :aria-pressed="locale === 'en'" @click="setLocale('en')">EN</button>
      </div>
    </nav>
    <article>
      <h1>{{ doc.title }}</h1>
      <p class="legal__updated">{{ doc.updated }}</p>
      <p class="legal__intro">{{ doc.intro }}</p>
      <section v-for="section in doc.sections" :key="section.title">
        <h2>{{ section.title }}</h2>
        <p v-for="(paragraph, index) in section.body" :key="index">{{ paragraph }}</p>
      </section>
    </article>
  </main>
</template>

<script setup lang="ts">
import { legal } from '~/locales/legal';

const props = defineProps<{ kind: 'privacy' | 'terms' }>();
const { locale, setLocale } = useLocale();
const doc = computed(() => legal[props.kind][locale.value]);
useHead(() => ({ title: `${doc.value.title} · VeiligerBuiten`, htmlAttrs: { lang: locale.value } }));
</script>

<style scoped>
.legal { min-height: 100vh; padding: 0 20px 80px; background: var(--vb-paper); }
.legal__nav { display: flex; align-items: center; justify-content: space-between; max-width: 720px; margin: 0 auto; padding: 22px 0; }
.legal__nav a { color: var(--vb-ink); text-decoration: none; }
.legal__lang { display: flex; gap: 4px; }
.legal__lang button { min-width: 40px; min-height: 32px; border: 0; border-radius: 9px; background: none; color: var(--vb-muted); font: inherit; font-size: 13px; font-weight: 600; cursor: pointer; }
.legal__lang button[aria-pressed='true'] { color: var(--vb-ink); background: #fff; box-shadow: 0 0 0 1px var(--vb-line); }
article { max-width: 720px; margin: 24px auto 0; padding: 40px clamp(20px, 5vw, 56px); border-radius: 28px; background: #fff; box-shadow: 0 0 0 1px var(--vb-line-2); }
h1 { margin: 0 0 6px; font-size: clamp(34px, 6vw, 48px); font-weight: 700; letter-spacing: -.045em; }
.legal__updated { margin: 0 0 24px; color: var(--vb-subtle); font-size: 14px; }
.legal__intro { margin: 0 0 32px; font-size: 18px; line-height: 1.55; }
h2 { margin: 32px 0 10px; font-size: 20px; font-weight: 650; letter-spacing: -.02em; }
p { margin: 0 0 12px; color: var(--vb-ink-3); font-size: 16px; line-height: 1.65; }
</style>
