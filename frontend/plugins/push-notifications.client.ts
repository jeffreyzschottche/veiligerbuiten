import { Capacitor } from '@capacitor/core';
import { PushNotifications } from '@capacitor/push-notifications';
import { apiFetch } from '~/services/apiFetch';

export default defineNuxtPlugin(async () => {
  if (!Capacitor.isNativePlatform()) return;
  const router = useRouter();
  await PushNotifications.addListener('pushNotificationActionPerformed', ({ notification }) => {
    const deliveryId = Number(notification.data?.delivery_id);
    if (Number.isInteger(deliveryId)) {
      useInstallation().ensure().then((token) => apiFetch(`/alert-deliveries/${deliveryId}/opened`, {
        method: 'POST', headers: { 'X-Installation-Token': token },
      })).catch(() => undefined);
    }
    const incidentId = Number(notification.data?.incident_id);
    const latitude = Number(notification.data?.latitude);
    const longitude = Number(notification.data?.longitude);
    if (!Number.isInteger(incidentId) || !Number.isFinite(latitude) || !Number.isFinite(longitude)) return;
    router.push({ path: '/', query: { incident: String(incidentId), lat: String(latitude), lng: String(longitude) } });
  });
});
