import { Capacitor } from '@capacitor/core';
import { PushNotifications } from '@capacitor/push-notifications';
import { apiFetch } from '~/services/apiFetch';

export function usePushNotifications() {
  async function enable() {
    if (!Capacitor.isNativePlatform()) return { enabled: false, reason: 'web' as const };
    const permission = await PushNotifications.requestPermissions();
    if (permission.receive !== 'granted') return { enabled: false, reason: 'denied' as const };

    const token = await useInstallation().ensure();
    const platform = Capacitor.getPlatform() === 'ios' ? 'ios' : 'android';
    await PushNotifications.addListener('registration', async ({ value }) => {
      await apiFetch('/installations/push-token', {
        method: 'PUT', headers: { 'X-Installation-Token': token }, body: JSON.stringify({ push_token: value, platform }),
      });
    });
    await PushNotifications.register();
    return { enabled: true as const };
  }

  return { enable };
}
