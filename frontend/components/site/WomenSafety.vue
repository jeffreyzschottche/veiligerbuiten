<template>
  <section id="women" class="women" aria-labelledby="women-heading">
    <div class="women-wrap">
      <div class="women-copy">
        <p class="women-eyebrow">{{ copy.eyebrow }}</p>
        <h2 id="women-heading">{{ copy.title }}</h2>
        <p class="women-lead">{{ copy.lead }}</p>
        <ol class="women-points">
          <li v-for="(point, index) in copy.points" :key="point.title"><span>0{{ index + 1 }}</span><div><h3>{{ point.title }}</h3><p>{{ point.text }}</p></div></li>
        </ol>
      </div>

      <!-- Zo ziet melden eruit: echte meldtypes uit de app, geen verzonnen functies. -->
      <div class="women-sheet" role="img" :aria-label="`${copy.sheetTitle}: ${copy.types.join(', ')}`">
        <p class="women-sheet__grip" />
        <p class="women-sheet__title">{{ copy.sheetTitle }}</p>
        <p class="women-sheet__category"><span><Icon name="hand" :size="16" /></span>{{ copy.sheetCategory }}</p>
        <ul>
          <li v-for="(type, index) in copy.types" :key="type" :class="{ 'is-picked': index === 1 }">{{ type }}<Icon name="chevron" :size="16" /></li>
        </ul>
        <p class="women-sheet__foot"><Icon name="lock" :size="14" />{{ copy.anonymous }}</p>
      </div>

      <div class="women-help">
        <p class="women-help__title">{{ copy.help.title }}</p>
        <a v-for="item in copy.help.items" :key="item.value" :href="item.href" class="women-help__item"><b>{{ item.value }}</b><span>{{ item.label }}</span></a>
        <p class="women-help__law">{{ copy.help.law }}</p>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import type { SiteCopy } from '~/locales/site';
defineProps<{ copy: SiteCopy['women'] }>();
</script>

<style scoped>
.women { padding: 108px 0; background: #fff; scroll-margin-top: 80px; }
.women-wrap { width: min(1200px, calc(100% - 48px)); margin: 0 auto; display: grid; grid-template-columns: 1.05fr .95fr; gap: 40px 72px; align-items: center; }
.women-eyebrow { margin: 0 0 18px; font: 10px var(--vb-mono); letter-spacing: .05em; text-transform: uppercase; color: var(--vb-brand); }
h2 { margin: 0; white-space: pre-line; font-size: clamp(38px, 4.1vw, 57px); line-height: 1.04; font-weight: 570; letter-spacing: -.06em; }
.women-lead { max-width: 34em; margin: 23px 0 28px; color: #657185; font-size: 15px; line-height: 1.75; }
.women-points { list-style: none; margin: 0; padding: 0; }
.women-points li { display: flex; gap: 17px; padding: 18px 0; border-top: 1px solid #e0e6ef; }
.women-points li > span { flex: 0 0 auto; margin-top: 4px; font: 10px var(--vb-mono); color: #7c92b2; }
h3 { margin: 0; font-size: 15px; line-height: 1.4; font-weight: 550; letter-spacing: -.02em; }
.women-points p { margin: 6px 0 0; color: #6d798c; font-size: 13px; line-height: 1.7; }

.women-sheet { justify-self: center; width: min(360px, 100%); padding: 10px 18px 18px; border-radius: 28px; background: var(--vb-paper); box-shadow: 0 30px 70px #0b0d121f, 0 0 0 1px #0b0d120d; }
.women-sheet__grip { width: 36px; height: 5px; margin: 0 auto 16px; border-radius: 9px; background: #d4d7de; }
.women-sheet__title { margin: 0 4px 12px; font-size: 22px; font-weight: 680; letter-spacing: -.03em; }
.women-sheet__category { display: inline-flex; align-items: center; gap: 8px; margin: 0 0 12px; padding: 6px 12px 6px 6px; border-radius: 99px; background: var(--vb-ink); color: #fff; font-size: 13px; font-weight: 600; }
.women-sheet__category span { display: grid; place-items: center; width: 26px; height: 26px; border-radius: 50%; background: #ffffff1f; }
.women-sheet ul { margin: 0; padding: 0; overflow: hidden; border-radius: 18px; background: #fff; box-shadow: 0 0 0 1px #e6e8ec; list-style: none; }
.women-sheet li { display: flex; align-items: center; justify-content: space-between; min-height: 48px; padding: 0 14px 0 16px; font-size: 14px; font-weight: 540; color: var(--vb-ink); }
.women-sheet li + li { box-shadow: inset 0 1px 0 #eef0f3; }
.women-sheet li :deep(svg) { color: #a3aab6; }
.women-sheet li.is-picked { background: var(--vb-brand-50); color: var(--vb-brand-600); }
.women-sheet__foot { display: flex; align-items: center; justify-content: center; gap: 6px; margin: 14px 0 0; color: #6d798c; font-size: 12px; }

.women-help { grid-column: 1 / -1; display: grid; grid-template-columns: auto repeat(3, 1fr); gap: 12px 16px; align-items: center; padding: 22px 24px; border-radius: 22px; background: #f4f6fa; }
.women-help__title { margin: 0; font-size: 15px; font-weight: 620; }
.women-help__item { display: flex; flex-direction: column; gap: 3px; padding: 12px 14px; border-radius: 14px; background: #fff; color: inherit; text-decoration: none; box-shadow: 0 0 0 1px #e6e8ec; transition: box-shadow .15s; }
.women-help__item:hover { box-shadow: 0 0 0 1.5px var(--vb-brand); }
.women-help__item b { font: 600 17px var(--vb-mono); letter-spacing: -.02em; }
.women-help__item span { color: #6d798c; font-size: 12px; line-height: 1.4; }
.women-help__law { grid-column: 1 / -1; margin: 2px 0 0; color: #6d798c; font-size: 12px; line-height: 1.6; }

@media (max-width: 899px) {
  .women { padding: 72px 0; }
  .women-wrap { width: calc(100% - 44px); grid-template-columns: 1fr; gap: 36px; }
  .women-help { grid-template-columns: 1fr; padding: 20px; }
}
</style>
