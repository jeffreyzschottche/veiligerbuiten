import { Capacitor } from '@capacitor/core';
import { App } from '@capacitor/app';
import { AdMob } from '@capacitor-community/admob';
import { createTrackingConsent } from '~/utils/trackingConsent';

async function whenActive() {
  if ((await App.getState()).isActive) return;
  await new Promise<void>((resolve, reject) => {
    let active = false;
    let remove: (() => Promise<void>) | undefined;
    const finish = () => { active = true; void remove?.(); resolve(); };
    App.addListener('appStateChange', ({ isActive }) => { if (isActive) finish(); })
      .then(async (listener) => {
        remove = () => listener.remove();
        if (active || (await App.getState()).isActive) { await listener.remove(); resolve(); }
      }).catch(reject);
  });
}

// ATT is independent of login, subscriptions, UMP geography and banner frequency.
export const ensureTrackingConsent = createTrackingConsent({
  isIos: () => Capacitor.getPlatform() === 'ios',
  whenActive,
  status: () => AdMob.trackingAuthorizationStatus(),
  request: () => AdMob.requestTrackingAuthorization(),
});
