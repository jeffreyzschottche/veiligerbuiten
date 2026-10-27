<template>
  <div class="vb-app account-action">
    <header class="account-action__header">
      <NuxtLink to="/" aria-label="VeiligerBuiten"><Logo inverse :size="30" /></NuxtLink>
      <div class="account-action__lang">
        <button :aria-pressed="locale === 'nl'" @click="setLocale('nl')">NL</button>
        <button :aria-pressed="locale === 'en'" @click="setLocale('en')">EN</button>
      </div>
    </header>
    <main class="account-action__card">
      <h1>{{ title }}</h1>
      <slot />
    </main>
  </div>
</template>

<script setup lang="ts">
defineProps<{ title: string }>();
const { locale, setLocale } = useLocale();
</script>

<style scoped>
.account-action { overflow-y: auto; padding: calc(var(--safe-top) + 20px) 16px calc(var(--safe-bottom) + 40px); background: var(--vb-ink); -webkit-user-select: auto; user-select: auto; }
.account-action__header { display: flex; align-items: center; justify-content: space-between; max-width: 460px; margin: 0 auto 28px; color: #fff; }
.account-action__header a { color: inherit; }
.account-action__lang { display: flex; gap: 4px; }
.account-action__lang button { min-width: 44px; min-height: 32px; border: 0; border-radius: 9px; color: #7d8492; background: none; font-size: 13px; font-weight: 600; }
.account-action__lang button[aria-pressed='true'] { color: #fff; background: #ffffff14; }
.account-action__card { max-width: 460px; margin: 0 auto; padding: 28px 20px; border-radius: 28px; background: var(--vb-paper); }
.account-action__card h1 { margin: 0 4px 18px; font-size: 30px; font-weight: 700; letter-spacing: -.04em; line-height: 1.15; }
.account-action__card :deep(p) { margin: 0 4px 20px; color: var(--vb-muted); font-size: 15px; line-height: 1.5; }
.account-action__card :deep(.error-text) { color: var(--vb-danger); }
.account-action__card :deep(.account-action__link) { display: block; margin-top: 18px; color: var(--vb-brand); text-align: center; font-size: 14px; }
</style>
