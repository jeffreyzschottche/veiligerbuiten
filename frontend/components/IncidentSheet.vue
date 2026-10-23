<template>
  <Transition name="sheet">
    <aside v-if="incident" class="incident-sheet" aria-live="polite">
      <button class="sheet-close" aria-label="Sluit incident" @click="$emit('close')"><Icon name="close" :size="18" /></button>
      <div class="incident-sheet__topline">
        <span class="category-icon" :class="`category-icon--${incident.category}`"><Icon :name="icon" :size="23" /></span>
        <div>
          <p class="eyebrow">{{ statusLabel }}</p>
          <h2>{{ incident.title }}</h2>
        </div>
      </div>
      <div class="incident-sheet__meta">
        <span><Icon name="locate" :size="14" />{{ incident.distance }}</span><span>·</span><span>{{ incident.reportedAt }}</span>
      </div>
      <div class="incident-sheet__source">{{ incident.source === 'seed' ? 'Voorbeelddata — geen echte melding' : incident.source === 'official' ? `Officiële bron · ${incident.sourceName ?? ''}` : `${incident.confirmations} aanwezig-signaal${incident.confirmations === 1 ? '' : 'en'} · ${incident.resolvedReports ?? 0} opgelost-signaal${(incident.resolvedReports ?? 0) === 1 ? '' : 'en'}` }}</div>
      <p class="incident-sheet__description">{{ incident.description }}</p>
      <p class="incident-sheet__advice">{{ incident.advice }}</p>
      <button class="action-secondary incident-sheet__street-view" @click="$emit('open-maps')">Bekijk in Street View <Icon name="external" :size="15" /></button>
      <button class="action-secondary incident-sheet__share" @click="$emit('share')">Deel deze locatie <Icon name="share" :size="15" /></button>
      <div class="incident-sheet__actions incident-sheet__vote-actions">
        <button class="action-primary" :class="{ selected: vote === 'present' }" :disabled="Boolean(vote)" @click="$emit('confirm')"><Icon name="check" :size="15" />Nog aanwezig <small>{{ incident.confirmations }}</small></button>
        <button class="action-resolved" :class="{ selected: vote === 'resolved' }" :disabled="Boolean(vote)" @click="$emit('resolved')"><Icon name="close" :size="15" />Niet meer aanwezig <small>{{ incident.resolvedReports ?? 0 }}</small></button>
      </div>
      <p v-if="vote" class="incident-sheet__vote-note">Jouw signaal is opgeslagen.</p>
    </aside>
  </Transition>
</template>

<script setup lang="ts">
import type { Incident } from '~/types/Incident';
const props = defineProps<{ incident: Incident | null; vote?: 'present' | 'resolved' | null }>();
defineEmits<{ close: []; confirm: []; resolved: []; 'open-maps': []; share: [] }>();
const statusLabel = computed(() => props.incident ? ({ gemeld: 'GEMELD', bevestigd: 'BEVESTIGD', officieel: 'OFFICIEEL', opgelost: 'OPGELOST', verlopen: 'VERLOPEN' }[props.incident.status]) : '');
const icon = computed(() => ({ brand: 'fire', geweld: 'shield-alert', onveilig: 'alert-triangle', verkeer: 'car', blokkade: 'construction', weer: 'cloud-rain', stoffen: 'flask', drukte: 'users', dier: 'paw', anders: 'circle-help' } as const)[props.incident?.category ?? 'anders']);
</script>
