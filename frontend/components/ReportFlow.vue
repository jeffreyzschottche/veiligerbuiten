<template>
  <Transition name="fade">
    <div v-if="open" class="report-overlay" @click.self="$emit('close')">
      <section class="report-flow" role="dialog" aria-modal="true" aria-label="Nieuwe melding">
        <template v-if="success">
          <div class="report-success"><div class="report-success__stars"><Icon v-for="star in 3" :key="star" name="star" :size="25" /></div><p class="eyebrow">MELDING ONTVANGEN</p><h2>Dank je, je helpt mee.</h2><p>Je houdt Buiten een stukje veiliger door actuele informatie te delen. De melding wordt nu als signaal in de omgeving verwerkt.</p><button class="submit-report" @click="$emit('close')">Terug naar de kaart</button></div>
        </template>
        <template v-else>
        <header><button aria-label="Sluit" @click="$emit('close')"><Icon name="close" :size="18" /></button><span>Nieuwe melding</span><span>{{ step }}/3</span></header>
        <template v-if="step === 1">
          <p class="eyebrow">WAT HEB JE WAARGENOMEN?</p><h2>Kies een categorie</h2>
          <div class="category-list">
            <button v-for="category in categories" :key="category.value" @click="choose(category.value)"><span class="report-category-icon"><Icon :name="category.icon" :size="18" /></span>{{ category.label }}<Icon name="chevron" :size="18" /></button>
          </div>
        </template>
        <template v-else-if="step === 2">
          <button class="back" @click="step = 1">← Categorie</button><p class="eyebrow">{{ selectedLabel }}</p><h2>Wat speelt er?</h2>
          <div class="category-list"><button v-for="option in subtypes" :key="option" @click="selectSubtype(option)">{{ option }}<Icon name="chevron" :size="18" /></button></div>
        </template>
        <template v-else>
          <button class="back" @click="step = 2">← Terug</button><p class="eyebrow">BIJNA KLAAR</p><h2>Voeg korte context toe</h2>
          <p class="report-note">Je locatie wordt gebruikt om de melding in je directe omgeving te plaatsen. {{ descriptionRequired ? 'Bij deze keuze is een korte toelichting verplicht.' : 'Een korte toelichting is optioneel.' }}</p>
          <textarea v-model="description" maxlength="180" :required="descriptionRequired" :placeholder="descriptionRequired ? 'Omschrijf kort wat er aan de hand is.' : 'Bijvoorbeeld: rook bij de ingang van het park.'" />
          <p v-if="descriptionError" class="report-error">{{ descriptionError }}</p>
          <CaptchaChallenge @verified="captchaToken = $event" />
          <p v-if="error" class="report-error">{{ error }}</p>
          <button class="submit-report" :disabled="submitting || !captchaToken || !!descriptionError || (descriptionRequired && !description.trim())" @click="submit">{{ submitting ? 'Melding controleren…' : 'Melding plaatsen' }}</button>
          <p class="safety-note">Bel 112 bij direct gevaar. Meld alleen wanneer je zelf veilig bent.</p>
        </template>
        </template>
      </section>
    </div>
  </Transition>
</template>

<script setup lang="ts">
import type { IncidentCategory } from '~/types/Incident';
import { reportCategories, reportSubtypes } from '~/utils/reportTaxonomy';
const props = defineProps<{ open: boolean; success?: boolean; submitting?: boolean; error?: string | null }>();
const emit = defineEmits<{ close: []; submit: [payload: { category: IncidentCategory; subtype: string; description: string; captchaToken: string }] }>();
const step = ref(1); const selected = ref<IncidentCategory>('brand'); const subtype = ref(''); const description = ref(''); const captchaToken = ref('');
const categories = reportCategories;
const selectedLabel = computed(() => categories.find((category) => category.value === selected.value)?.label ?? '');
const subtypes = computed(() => reportSubtypes[selected.value]);
const descriptionRequired = computed(() => selected.value === 'anders' || subtype.value.startsWith('Anders:'));
const descriptionError = computed(() => {
  const value = description.value.trim();
  if (!value) return descriptionRequired.value ? 'Geef bij Anders een korte toelichting.' : '';
  if (/\d/u.test(value)) return 'Je mag geen cijfers gebruiken.';
  if (!/^[\p{L}\s,.!]+$/u.test(value)) return 'Gebruik alleen letters, spaties, komma’s, punten en uitroeptekens.';
  if (/\b(https?|www|\.nl|\.com|gmail|hotmail|outlook)\b/ui.test(value) || /[\p{L}._%+-]+@[\p{L}.-]+\.[\p{L}]{2,}/u.test(value)) return 'Links, e-mailadressen en contactgegevens zijn niet toegestaan.';
  if (/\b(korting|aanbieding|koop|volg mij|instagram|telegram|whatsapp|dm)\b/ui.test(value)) return 'Promotie en reclame zijn niet toegestaan.';
  if (/\b(kanker|tering|hoer|kut|neuk|fuck)\b/ui.test(value)) return 'Scheldwoorden zijn niet toegestaan.';
  if (/(.)\1{4,}/u.test(value) || /\b([\p{L}]+)(?:\s+\1){2,}\b/ui.test(value)) return 'De omschrijving lijkt spam te bevatten.';
  return '';
});
function choose(category: IncidentCategory) { selected.value = category; step.value = 2; }
function selectSubtype(value: string) { subtype.value = value; step.value = 3; }
function submit() { if (!captchaToken.value || descriptionError.value) return; emit('submit', { category: selected.value, subtype: subtype.value || subtypes.value[0], description: description.value.trim(), captchaToken: captchaToken.value }); }
watch(() => props.open, (isOpen) => { if (isOpen) { step.value = 1; subtype.value = ''; description.value = ''; captchaToken.value = ''; } });
</script>
