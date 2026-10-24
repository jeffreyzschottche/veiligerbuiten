<template>
  <section class="offline-story" aria-labelledby="offline-heading">
    <div class="offline-wrap">
      <div class="offline-copy">
        <p class="offline-eyebrow">{{ copy.eyebrow }}</p>
        <h2 id="offline-heading">{{ copy.title }}</h2>
        <p class="offline-lead">{{ copy.lead }}</p>
        <ol class="offline-steps"><li v-for="(step, index) in copy.steps" :key="index"><span>0{{ index + 1 }}</span><div><h3>{{ step.title }}</h3><p>{{ step.text }}</p></div></li></ol>
      </div>
      <div class="offline-demo" role="img" :aria-label="`${copy.saved}. ${copy.you}. ${copy.notification}.`">
        <MiniMap />
        <div class="offline-demo__shade" />
        <span class="offline-demo__saved"><Icon name="check" :size="13" />{{ copy.saved }}</span>
        <svg class="offline-demo__route" viewBox="0 0 480 500" fill="none" aria-hidden="true"><path class="route-shadow" d="M100 370H210Q250 370 250 330V200Q250 160 290 160H340" /><path class="route-line" d="M100 370H210Q250 370 250 330V200Q250 160 290 160H340" /></svg>
        <span class="offline-demo__person"><i /><span>{{ copy.you }}</span></span>
        <span class="offline-demo__incident"><i /><span>{{ copy.report }}</span></span>
        <div class="offline-notification"><LogoMark :size="31" /><div><span>VEILIGBUITEN <small>{{ copy.example }}</small></span><strong>{{ copy.notification }}</strong><p>{{ copy.notificationBody }}</p></div></div>
        <span class="offline-demo__connection"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="m3 3 18 18M2 8.5a16 16 0 0 1 3.6-2.2M8.8 5.3A16 16 0 0 1 22 8.5M5 12a11 11 0 0 1 3.2-1.8M12.8 9.8A11 11 0 0 1 19 12M8.5 15.5a5.5 5.5 0 0 1 6.9 0M12 19h.01" /></svg>{{ copy.connection }}</span>
      </div>
      <p class="offline-note"><Icon name="info" :size="16" /><span>{{ copy.note }}</span></p>
    </div>
  </section>
</template>

<script setup lang="ts">
import type { SiteCopy } from '~/locales/site';
defineProps<{ copy: SiteCopy['offline'] }>();
</script>

<style scoped>
.offline-story { background: #f9fafb; padding: 108px 0; }
.offline-wrap { width: min(1200px, calc(100% - 48px)); margin: 0 auto; display: grid; grid-template-columns: 1fr 1fr; gap: 35px 70px; align-items: center; }
.offline-eyebrow { margin: 0 0 18px; font: 10px var(--vb-mono); letter-spacing: .05em; text-transform: uppercase; color: #2f6bff; }
h2 { margin: 0; white-space: pre-line; font-size: clamp(38px,4.1vw,57px); line-height: 1.04; font-weight: 570; letter-spacing: -.06em; }
.offline-lead { margin: 23px 0 28px; color: #657185; font-size: 15px; line-height: 1.75; }
.offline-steps { list-style: none; margin: 0; padding: 0; }
.offline-steps li { display: flex; gap: 17px; padding: 18px 0; border-top: 1px solid #e0e6ef; }
.offline-steps li > span { flex: 0 0 auto; font: 10px var(--vb-mono); color: #7c92b2; margin-top: 4px; }
h3 { margin: 0; font-size: 15px; line-height: 1.4; font-weight: 550; letter-spacing: -.02em; }
.offline-steps p { margin: 6px 0 0; color: #6d798c; font-size: 13px; line-height: 1.7; }
.offline-demo { position: relative; min-height: 520px; border: 1px solid #dfe7f2; border-radius: 25px; overflow: hidden; background: #e8eff9; }
.offline-demo > :deep(.mini-map) { position: absolute; width: 100%; height: 100%; opacity: .55; }
.offline-demo__shade { position: absolute; inset: 0; background: linear-gradient(180deg,#eef3faf2,transparent 40%,#edf3faef); }
.offline-demo__saved { position: absolute; display: inline-flex; align-items: center; gap: 7px; top: 23px; left: 23px; padding: 10px 12px; border-radius: 99px; color: #3c6d63; background: #ffffffed; font-size: 10px; border: 1px solid #dce8e5; }
.offline-demo__route { position: absolute; inset: 0; width: 100%; height: 100%; }
.route-shadow { stroke: #fff; stroke-width: 11; stroke-linecap: round; }
.route-line { stroke: #6d99ed; stroke-width: 3; stroke-linecap: round; stroke-dasharray: 5 8; }
.offline-demo__person, .offline-demo__incident { position: absolute; display: flex; align-items: center; gap: 11px; }
.offline-demo__person { top: 72%; left: 18%; }
.offline-demo__incident { top: 30%; left: 65%; flex-direction: column; align-items: end; transform: translateX(-35%); }
.offline-demo__person > i, .offline-demo__incident > i { display: block; flex: 0 0 auto; width: 12px; height: 12px; border: 3px solid #fff; border-radius: 50%; background: #2f6bff; box-shadow: 0 0 0 9px #2f6bff1c,0 3px 9px #20365420; }
.offline-demo__incident > i { width: 15px; height: 15px; background: #ff8a00; box-shadow: 0 0 0 12px #ff8a0015,0 3px 9px #20365420; }
.offline-demo__person > span, .offline-demo__incident > span { font-size: 9px; color: #526787; background: #ffffffe0; padding: 6px 8px; border-radius: 5px; white-space: nowrap; }
.offline-notification { position: absolute; left: 8%; right: 8%; top: 44%; display: flex; gap: 10px; align-items: start; padding: 16px; border-radius: 16px; background: #fffffff5; border: 1px solid #fff; box-shadow: 0 10px 30px #324d7420; transform: rotate(-3deg); }
.offline-notification > div { flex: 1; min-width: 0; }
.offline-notification span { display: flex; justify-content: space-between; gap: 8px; font-size: 8px; color: #75849a; margin-bottom: 6px; font-weight: 550; letter-spacing: .035em; }
.offline-notification small { font-size: 8px; font-weight: 400; letter-spacing: 0; }
.offline-notification strong { font-size: 14px; font-weight: 600; letter-spacing: -.02em; }
.offline-notification p { margin: 5px 0 0; font-size: 10px; line-height: 1.6; color: #66758c; }
.offline-demo__connection { position: absolute; bottom: 27px; left: 0; right: 0; display: flex; align-items: center; justify-content: center; gap: 8px; color: #6a7c97; font-size: 10px; }
.offline-note { grid-column: 1 / -1; display: flex; gap: 11px; align-items: start; margin: 0; padding: 18px 21px; border: 1px solid #e1e7f0; border-radius: 12px; background: #f0f4f9; color: #738198; font-size: 12px; line-height: 1.7; }
.offline-note > svg { flex: 0 0 auto; margin-top: 2px; }
@media (max-width: 979px) { .offline-wrap { gap: 30px; } .offline-demo { min-height: 490px; } .offline-demo__saved { font-size: 9px; left: 15px; } .offline-notification { left: 5%; right: 5%; padding: 12px; } }
@media (max-width: 767px) { .offline-story { padding: 72px 0; } .offline-wrap { width: calc(100% - 44px); grid-template-columns: 1fr; gap: 25px; } h2 { font-size: 40px; } .offline-lead { font-size: 14px; } .offline-demo { min-height: 420px; } .offline-demo__saved { top: 20px; left: 20px; font-size: 10px; } .offline-note { font-size: 11px; padding: 16px; } }
@media (max-width: 374px) { .offline-wrap { width: calc(100% - 34px); } h2 { font-size: 36px; } .offline-demo__connection { font-size: 9px; } }
</style>
