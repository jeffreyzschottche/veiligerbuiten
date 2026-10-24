<template>
  <div class="auth">
    <NuxtLink to="/" class="auth__logo" aria-label="VeiligBuiten"><Logo /></NuxtLink>
    <div class="auth__card">
      <slot />
    </div>
  </div>
</template>

<style scoped>
.auth { min-height: 100vh; padding: calc(env(safe-area-inset-top, 0px) + 32px) 20px 40px; background: var(--vb-paper); }
.auth__logo { display: flex; justify-content: center; margin-bottom: 28px; color: var(--vb-ink); text-decoration: none; }
.auth__card { max-width: 420px; margin: 0 auto; }
.auth__card :deep(.shadow) { box-shadow: 0 0 0 1px var(--vb-line-2) !important; border-radius: 24px !important; }
.auth__card :deep(button[type='submit']) { min-height: 52px; border-radius: 16px; background: var(--vb-ink); }
.auth__card :deep(input) { min-height: 48px; border-radius: 12px; }
</style>
