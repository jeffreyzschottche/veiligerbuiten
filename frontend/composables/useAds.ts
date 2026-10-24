import { Capacitor, type PluginListenerHandle } from '@capacitor/core';
import { AdMob, BannerAdPluginEvents, BannerAdPosition, BannerAdSize, AdmobConsentStatus } from '@capacitor-community/admob';
import { storage } from '~/utils/storage';

/** Google's officiële test-units: veilig in ontwikkeling, geen ongeldige klikken. */
const TEST_UNITS = { ios: 'ca-app-pub-3940256099942544/2435281174', android: 'ca-app-pub-3940256099942544/9214589741' };
const STATE_KEY = 'vb_ads';
/** Pas na een paar keer openen: de eerste indruk is de app, niet een advertentie. */
const MIN_OPENS = 3;
/** Hoogstens eens per drie uur en vier keer per dag, en dan maar 45 seconden. */
const COOLDOWN_MS = 3 * 60 * 60 * 1000;
const MAX_PER_DAY = 4;
const VISIBLE_MS = 45 * 1000;
/** Tabbalk (64) plus een kleine marge, zodat niemand per ongeluk op de advertentie tikt. */
const ABOVE_TABBAR_DP = 70;

interface AdState { opens: number; lastShown: number; day: string; shownToday: number }

/**
 * Af en toe een kleine banner boven de tabbalk, zonder de app te verstoren:
 *  - niet bij de eerste paar keer openen, en niet als je via een melding binnenkomt;
 *  - nooit als er gevaar in de buurt is of als je iets aan het doen bent (sheet open, ander tabblad);
 *  - hoogstens eens per drie uur, vier keer per dag, en na 45 seconden weer weg.
 * De tabbalk blijft waar hij is; alleen het paneel schuift even omhoog.
 */
export function useAds() {
  const height = useState('ads-height', () => 0);
  const visible = useState('ads-visible', () => false);
  const ready = useState('ads-ready', () => false);
  const native = Capacitor.isNativePlatform();
  const config = useRuntimeConfig().public;
  const testing = !!config.admobTesting || import.meta.dev;
  const unit = Capacitor.getPlatform() === 'ios'
    ? String(testing ? TEST_UNITS.ios : config.admobBannerIos || '')
    : String(testing ? TEST_UNITS.android : config.admobBannerAndroid || '');
  let hideTimer: ReturnType<typeof setTimeout> | undefined;
  const listeners: PluginListenerHandle[] = [];

  async function init() {
    if (!native || !unit || ready.value) return ready.value;
    try {
      // Eerst toestemming (AVG/TCF via Google UMP); zonder toestemming geen advertentieverzoeken.
      const consent = await AdMob.requestConsentInfo();
      let canRequest = consent.canRequestAds;
      if (consent.isConsentFormAvailable && consent.status === AdmobConsentStatus.REQUIRED) {
        canRequest = (await AdMob.showConsentForm()).canRequestAds;
      }
      if (!canRequest) return false;
      if (Capacitor.getPlatform() === 'ios' && (await AdMob.trackingAuthorizationStatus()).status === 'notDetermined') {
        await AdMob.requestTrackingAuthorization();
      }
      await AdMob.initialize({ initializeForTesting: testing });
      listeners.push(await AdMob.addListener(BannerAdPluginEvents.SizeChanged, ({ height: h }) => { height.value = visible.value ? h : 0; }));
      listeners.push(await AdMob.addListener(BannerAdPluginEvents.FailedToLoad, () => { void hide(); }));
      ready.value = true;
    } catch {
      ready.value = false;
    }
    return ready.value;
  }

  async function state(): Promise<AdState> {
    const today = new Date().toDateString();
    const saved = await storage.get<AdState>(STATE_KEY);
    return saved && saved.day === today ? saved : { opens: saved?.opens ?? 0, lastShown: saved?.lastShown ?? 0, day: today, shownToday: 0 };
  }

  /** Bij elke keer dat de app opent of terugkomt. */
  async function countOpen() {
    const current = await state();
    await storage.set(STATE_KEY, { ...current, opens: current.opens + 1 });
  }

  async function maybeShow(context: { calm: boolean; idle: boolean; fromNotification: boolean }) {
    if (!native || visible.value || !context.calm || !context.idle || context.fromNotification) return;
    const current = await state();
    if (current.opens < MIN_OPENS || current.shownToday >= MAX_PER_DAY || Date.now() - current.lastShown < COOLDOWN_MS) return;
    if (!(await init())) return;
    try {
      visible.value = true;
      await AdMob.showBanner({ adId: unit, adSize: BannerAdSize.ADAPTIVE_BANNER, position: BannerAdPosition.BOTTOM_CENTER, margin: ABOVE_TABBAR_DP, isTesting: testing });
      await storage.set(STATE_KEY, { ...current, lastShown: Date.now(), shownToday: current.shownToday + 1 });
      clearTimeout(hideTimer);
      hideTimer = setTimeout(() => { void hide(); }, VISIBLE_MS);
    } catch {
      visible.value = false;
    }
  }

  async function hide() {
    clearTimeout(hideTimer);
    if (!visible.value) return;
    visible.value = false;
    height.value = 0;
    await AdMob.removeBanner().catch(() => undefined);
  }

  /** Privacy-instellingen voor advertenties opnieuw openen (verplicht aan te bieden onder de AVG). */
  async function privacyOptions() {
    if (!native) return;
    await AdMob.showPrivacyOptionsForm().catch(() => undefined);
  }

  function dispose() {
    clearTimeout(hideTimer);
    listeners.splice(0).forEach((listener) => listener.remove());
  }

  return { height, visible, available: native && !!unit, countOpen, maybeShow, hide, privacyOptions, dispose };
}
