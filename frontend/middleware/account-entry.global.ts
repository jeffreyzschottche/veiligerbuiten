import { Capacitor } from '@capacitor/core';

/** Eén registratie- en betaalpagina op het web; de mobiele app logt alleen in. */
export default defineNuxtRouteMiddleware((to) => {
  // Tijdens statisch bouwen kennen we het toestel nog niet; de app handelt dit bij openen af.
  if (import.meta.server && useRuntimeConfig().public.mobileBuild) return;
  const path = to.path.replace(/\/+$/, '') || '/';

  if (Capacitor.isNativePlatform()) {
    if (['/aanmelden', '/account', '/register'].includes(path)) {
      return navigateTo('/app/start', { replace: true });
    }
    return;
  }

  if (['/login', '/app/start'].includes(path)) {
    return navigateTo('/account', { replace: true });
  }
  if (path === '/register') {
    return navigateTo('/aanmelden', { replace: true });
  }
});
