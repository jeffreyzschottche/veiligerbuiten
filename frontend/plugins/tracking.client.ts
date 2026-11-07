import { ensureTrackingConsent } from '~/composables/useTrackingConsent';

export default defineNuxtPlugin((nuxtApp) => {
  nuxtApp.hook('app:mounted', () => {
    void ensureTrackingConsent().catch(() => undefined);
  });
});
