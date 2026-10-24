import { Capacitor } from '@capacitor/core';
import { LocalNotifications } from '@capacitor/local-notifications';
import { PushNotifications } from '@capacitor/push-notifications';
import { Geolocation } from '@capacitor/geolocation';
import { storage } from '~/utils/storage';

const INSTALLATION_KEY = 'vb_installation';

/**
 * Android-kanalen per ernst. Gebruikers kunnen ze los van elkaar dempen;
 * de server en de achtergrondcontrole gebruiken dezelfde id's.
 */
const CHANNELS = [
  { id: 'vb_danger', name: { nl: 'Gevaar', en: 'Danger' }, importance: 5 as const, sound: true },
  { id: 'vb_warn', name: { nl: 'Opletten', en: 'Heads-up' }, importance: 4 as const, sound: true },
  { id: 'vb_info', name: { nl: 'Goed om te weten', en: 'Good to know' }, importance: 2 as const, sound: false },
];

/** Dit toestel: installatietoken, pushtoken, meldingskanalen en rechten. */
export function useDevice() {
  const token = useState<string | null>('installation-token', () => null);
  const native = Capacitor.isNativePlatform();
  const platform = Capacitor.getPlatform();

  async function ensureInstallation() {
    if (token.value) return token.value;
    // Oudere versies bewaarden het token als platte string in localStorage.
    const stored = await storage.get<string>(INSTALLATION_KEY) ?? localStorage.getItem('veiligbuiten-installation');
    if (stored) {
      token.value = stored;
      await storage.set(INSTALLATION_KEY, stored);
      return stored;
    }
    const { data } = await useApi().post<{ data: { token: string } }>('/installations');
    token.value = data.token;
    await storage.set(INSTALLATION_KEY, data.token);
    return data.token;
  }

  /** Koppelt dit toestel aan het account, zodat meldingen voor je plekken hier binnenkomen. */
  async function link(unlink = false) {
    await ensureInstallation();
    await useApi().put('/installations/link', { unlink }).catch(() => undefined);
  }

  async function createChannels() {
    if (platform !== 'android') return;
    const { locale } = useLocale();
    for (const channel of CHANNELS) {
      // Importance 2 (laag) is op Android zonder geluid; 4 en 5 met geluid, 5 ook als banner.
      const options = { id: channel.id, name: channel.name[locale.value], importance: channel.importance, visibility: 1 as const, vibration: channel.sound };
      await LocalNotifications.createChannel(options).catch(() => undefined);
      await PushNotifications.createChannel(options).catch(() => undefined);
    }
  }

  async function notificationPermission() {
    if (!native) return 'denied';
    return (await PushNotifications.checkPermissions()).receive;
  }

  /** Vraagt toestemming en registreert het pushtoken bij de server. */
  async function enableNotifications() {
    if (!native) return false;
    const permission = await PushNotifications.requestPermissions();
    await LocalNotifications.requestPermissions().catch(() => undefined);
    if (permission.receive !== 'granted') return false;
    await createChannels();
    await registerPush();
    return true;
  }

  let registering = false;
  async function registerPush() {
    if (!native || registering) return;
    if ((await PushNotifications.checkPermissions()).receive !== 'granted') return;
    registering = true;
    await ensureInstallation();
    await PushNotifications.removeAllListeners().catch(() => undefined);
    await PushNotifications.addListener('registration', ({ value }) => {
      useApi().put('/installations/push-token', { push_token: value, platform: platform === 'ios' ? 'ios' : 'android' }).catch(() => undefined);
    });
    await PushNotifications.addListener('pushNotificationActionPerformed', ({ notification }) => openFromNotification(notification.data));
    await PushNotifications.register();
    registering = false;
  }

  async function locationPermission() {
    if (!native) {
      const status = await navigator.permissions?.query({ name: 'geolocation' as PermissionName }).catch(() => null);
      return status?.state ?? 'prompt';
    }
    return (await Geolocation.checkPermissions()).location;
  }

  async function enableLocation() {
    if (!native) return !!(await currentPosition().catch(() => null));
    const result = await Geolocation.requestPermissions({ permissions: ['location'] }).catch(() => null);
    return result?.location === 'granted';
  }

  return { token, native, platform, ensureInstallation, link, createChannels, notificationPermission, enableNotifications, registerPush, locationPermission, enableLocation };
}

/** Een tik op een melding opent het incident op de kaart. */
export function openFromNotification(data: Record<string, unknown> | undefined) {
  const deliveryId = Number(data?.delivery_id);
  if (Number.isInteger(deliveryId) && deliveryId > 0) useApi().post(`/alert-deliveries/${deliveryId}/opened`).catch(() => undefined);
  const incident = Number(data?.incident_id);
  if (Number.isInteger(incident) && incident > 0) navigateTo({ path: '/app', query: { incident: String(incident) } });
}

export interface Position { latitude: number; longitude: number; accuracy: number }

export async function currentPosition(highAccuracy = false): Promise<Position> {
  if (Capacitor.isNativePlatform()) {
    const { coords } = await Geolocation.getCurrentPosition({ enableHighAccuracy: highAccuracy, timeout: 10_000, maximumAge: highAccuracy ? 0 : 120_000 });
    return { latitude: coords.latitude, longitude: coords.longitude, accuracy: coords.accuracy };
  }
  return new Promise((resolve, reject) => {
    if (!navigator.geolocation) return reject(new Error('geolocation'));
    navigator.geolocation.getCurrentPosition(
      ({ coords }) => resolve({ latitude: coords.latitude, longitude: coords.longitude, accuracy: coords.accuracy }),
      reject,
      { enableHighAccuracy: highAccuracy, timeout: 10_000, maximumAge: highAccuracy ? 0 : 120_000 },
    );
  });
}
