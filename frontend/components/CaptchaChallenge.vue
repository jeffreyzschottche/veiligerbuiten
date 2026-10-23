<template>
  <div class="captcha-challenge" :class="{ 'is-ready': token, 'is-error': error }">
    <div class="captcha-challenge__row">
      <Icon :name="token ? 'check' : 'shield-alert'" :size="17" />
      <span>{{ token ? 'Beveiligingscontrole voltooid' : status }}</span>
    </div>
    <button v-if="error" type="button" class="captcha-challenge__retry" @click="start">Opnieuw proberen</button>
  </div>
</template>

<script setup lang="ts">
import { apiFetch } from '~/services/apiFetch';
type Challenge = { salt: string; expiresAt: number; difficulty: number; signature: string };
const emit = defineEmits<{ verified: [token: string] }>();
const token = ref(''); const status = ref('Beveiligingscontrole uitvoeren…'); const error = ref(false);

async function digest(value: string) {
  const bytes = new TextEncoder().encode(value);
  const hash = await crypto.subtle.digest('SHA-256', bytes);
  return Array.from(new Uint8Array(hash)).map((byte) => byte.toString(16).padStart(2, '0')).join('');
}

async function solve(challenge: Challenge) {
  const prefix = '0'.repeat(challenge.difficulty);
  for (let nonce = 0; nonce < 1_000_000; nonce += 1) {
    if ((nonce & 127) === 0) await new Promise<void>((resolve) => setTimeout(resolve));
    if ((await digest(`${challenge.salt}:${nonce}`)).startsWith(prefix)) return `${challenge.salt}.${challenge.expiresAt}.${challenge.difficulty}.${challenge.signature}.${nonce}`;
  }
  throw new Error('Geen oplossing gevonden');
}

async function start() {
  token.value = ''; error.value = false; status.value = 'Beveiligingscontrole uitvoeren…';
  try {
    const response = await apiFetch<{ data: Challenge }>('/captcha/challenge');
    token.value = await solve(response.data);
    emit('verified', token.value);
  } catch {
    error.value = true; status.value = 'Beveiligingscontrole kon niet starten.';
  }
}
onMounted(start);
</script>
