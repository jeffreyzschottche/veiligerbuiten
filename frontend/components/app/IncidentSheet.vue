<template>
  <BottomSheet :open="!!incident" :backdrop="false" @close="emit('close')">
    <template v-if="incident" #head>
      <div class="inc-head">
        <span class="lv-tag" :class="`lv-${incident.level}`"><span class="lv-dot" />{{ t.levels[incident.level] }}</span>
        <h2 class="sheet__title">{{ incident.title }}</h2>
      </div>
    </template>
    <template v-if="incident">
      <p class="inc-meta">
        <span v-if="meters !== null">{{ fill(t.incident.away, { d: distance(meters) }) }} · </span>{{ ago(incident.updatedAt) }}
      </p>
      <p v-if="incident.description" class="inc-desc">{{ incident.description }}</p>

      <div class="inc-facts card">
        <div class="row">
          <span class="row__icon"><Icon name="users" :size="18" /></span>
          <span class="row__main">
            <span class="row__title">{{ confirmationText }}</span>
            <span class="row__sub">{{ incident.official ? fill(t.incident.source, { s: incident.sourceName ?? '—' }) : t.incident.community }}</span>
          </span>
        </div>
        <div class="row">
          <span class="row__icon"><Icon name="bell" :size="18" /></span>
          <span class="row__main">
            <span class="row__title">{{ fill(t.incident.reach, { d: distance(incident.alertRadius) }) }}</span>
            <span class="row__sub">{{ categoryLabel(incident.category, locale) }}</span>
          </span>
        </div>
      </div>

      <div v-if="!voted" class="inc-vote">
        <button class="btn btn--ink" :disabled="voting" @click="emit('vote', 'present')"><Icon name="check" :size="18" />{{ t.incident.confirm }}</button>
        <button class="btn btn--soft" :disabled="voting" @click="emit('vote', 'resolved')">{{ t.incident.resolve }}</button>
      </div>
      <p v-else class="inc-voted"><Icon name="check" :size="16" />{{ t.incident.voted }}</p>

      <div class="inc-actions">
        <button class="btn btn--soft btn--sm" @click="route"><Icon name="navigation" :size="16" />{{ t.incident.route }}</button>
        <button class="btn btn--soft btn--sm" @click="share"><Icon name="share" :size="16" />{{ t.incident.share }}</button>
      </div>
      <p class="inc-112">{{ t.common.call112 }}</p>
    </template>
  </BottomSheet>
</template>

<script setup lang="ts">
import { Capacitor } from '@capacitor/core';
import { Share } from '@capacitor/share';
import type { Incident } from '~/types/Incident';
import { categoryLabel } from '~/utils/taxonomy';

const props = defineProps<{ incident: Incident | null; meters: number | null; voted: boolean; voting: boolean }>();
const emit = defineEmits<{ close: []; vote: [vote: 'present' | 'resolved'] }>();
const { t, locale, fill, distance, ago } = useAppCopy();

const confirmationText = computed(() => {
  const n = props.incident?.confirmations ?? 0;
  if (n === 0) return t.value.incident.confirmationsNone;
  return n === 1 ? t.value.incident.confirmationsOne : fill(t.value.incident.confirmations, { n });
});

function route() {
  if (!props.incident) return;
  const { latitude, longitude } = props.incident;
  const url = Capacitor.getPlatform() === 'ios'
    ? `https://maps.apple.com/?daddr=${latitude},${longitude}`
    : `https://www.google.com/maps/dir/?api=1&destination=${latitude},${longitude}`;
  window.open(url, '_system');
}

async function share() {
  if (!props.incident) return;
  const text = fill(t.value.incident.shareText, { title: props.incident.title });
  const url = `https://veiligbuiten.nl/app?incident=${props.incident.id}`;
  if (Capacitor.isNativePlatform()) await Share.share({ text, url }).catch(() => undefined);
  else if (navigator.share) await navigator.share({ text, url }).catch(() => undefined);
  else await navigator.clipboard?.writeText(`${text} ${url}`);
}
</script>

<style scoped>
.inc-head { display: flex; flex: 1; flex-direction: column; align-items: flex-start; gap: 8px; min-width: 0; }
.inc-head .sheet__title { font-size: 24px; line-height: 1.15; }
.inc-meta { margin: 0 0 14px; color: var(--vb-muted); font-size: 14px; }
.inc-desc { margin: 0 0 16px; font-size: 15px; line-height: 1.45; }
.inc-facts { margin-bottom: 16px; }
.inc-vote { display: grid; grid-template-columns: 1.3fr 1fr; gap: 10px; margin-bottom: 12px; }
.inc-voted { display: flex; align-items: center; justify-content: center; gap: 8px; min-height: 52px; margin: 0 0 12px; border-radius: 16px; color: var(--vb-ok); background: #e8f5ee; font-weight: 560; }
.inc-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.inc-112 { margin: 16px 0 0; color: var(--vb-subtle); font-size: 12px; text-align: center; }
</style>
