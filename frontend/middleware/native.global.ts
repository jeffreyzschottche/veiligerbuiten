import { Capacitor } from '@capacitor/core';

/** In de app is er geen landingspagina: de telefoon opent direct de kaart. */
export default defineNuxtRouteMiddleware((to) => {
  if (import.meta.client && Capacitor.isNativePlatform() && to.path === '/') {
    return navigateTo('/app', { replace: true });
  }
});
