import { Capacitor } from '@capacitor/core';
import { Purchases, type PurchasesPackage } from '@revenuecat/purchases-capacitor';

/**
 * Abonnement van € 0,99 per maand, geen proefperiode. Eén abonnement per account:
 *  - in de app via de App Store of Google Play (RevenueCat), want daar moet het via de winkel;
 *  - op de website via Stripe.
 * In de app staat geen link naar de betaalpagina op de website (dat staan de winkels niet toe).
 */
export function useBilling() {
  const configuredFor = useState<number | null>('billing-user', () => null);
  const monthly = useState<PurchasesPackage | null>('billing-package', () => null);
  const busy = useState('billing-busy', () => false);
  const native = Capacitor.isNativePlatform();
  /** Abonneren kan overal: in de app via de winkel, op de website via Stripe. */
  const available = true;

  async function configure() {
    const auth = useAuthStore();
    if (!native || !auth.account) return false;
    if (configuredFor.value === auth.account.id) return true;
    const config = useRuntimeConfig().public;
    const apiKey = String(Capacitor.getPlatform() === 'ios' ? config.revenuecatIosKey : config.revenuecatAndroidKey);
    if (!apiKey) return false;
    // De app-user-id is het account-id: zo koppelt de webhook de aankoop aan het juiste account.
    if (configuredFor.value === null) await Purchases.configure({ apiKey, appUserID: String(auth.account.id) });
    else await Purchases.logIn({ appUserID: String(auth.account.id) });
    configuredFor.value = auth.account.id;
    return true;
  }

  /** Het maandpakket uit de winkel, met de prijs in de valuta van de gebruiker. */
  async function loadOffer() {
    if (!(await configure().catch(() => false))) return null;
    const offerings = await Purchases.getOfferings().catch(() => null);
    monthly.value = offerings?.current?.monthly ?? offerings?.current?.availablePackages[0] ?? null;
    return monthly.value;
  }

  /** Op de website: naar Stripe Checkout (of de beheerpagina als er al een abonnement loopt). */
  async function checkout() {
    await go('/me/subscription/checkout');
  }

  /** In de app: kopen in de winkel. */
  async function purchase() {
    const offer = monthly.value ?? await loadOffer();
    if (!offer) throw new Error('store_unavailable');
    busy.value = true;
    try {
      await Purchases.purchasePackage({ aPackage: offer });
      return await syncWithServer();
    } catch (error: any) {
      if (error?.userCancelled || error?.code === '1') return false;
      throw error;
    } finally {
      busy.value = false;
    }
  }

  /** Eerder in de winkel gekocht, bijvoorbeeld op een vorige telefoon. */
  async function restore() {
    if (!(await configure())) return false;
    busy.value = true;
    try {
      await Purchases.restorePurchases();
      return await syncWithServer();
    } finally {
      busy.value = false;
    }
  }

  /** Meteen de status ophalen, zonder op de webhook te wachten. */
  async function syncWithServer(sessionId?: string) {
    const auth = useAuthStore();
    const body = sessionId ? { session_id: sessionId, source: 'web' } : native ? { source: 'store' } : {};
    await useApi().post('/me/subscription/refresh', body).catch(() => undefined);
    await auth.refreshAccount();
    return auth.isSubscribed;
  }

  /** Beheren waar het abonnement loopt: winkel of Stripe. Null = in de app niet te beheren (loopt via de website). */
  function manageTarget(): 'app_store' | 'play_store' | 'stripe' | null {
    const provider = useAuthStore().account?.subscription.provider;
    if (provider === 'app_store' || provider === 'play_store') return provider;
    if (provider === 'stripe' && !native) return 'stripe';
    return null;
  }

  async function manage() {
    const target = manageTarget();
    if (target === 'stripe') return go('/me/subscription/portal');
    const url = target === 'app_store'
      ? 'https://apps.apple.com/account/subscriptions'
      : 'https://play.google.com/store/account/subscriptions?package=nl.veiligerbuiten.app';
    window.open(url, native ? '_system' : '_blank');
  }

  async function go(endpoint: string) {
    busy.value = true;
    try {
      const { url } = await useApi().post<{ url: string }>(endpoint);
      window.location.assign(url);
    } finally {
      busy.value = false;
    }
  }

  function reset() {
    if (configuredFor.value !== null && native) Purchases.logOut().catch(() => undefined);
    configuredFor.value = null;
    monthly.value = null;
  }

  return { native, available, monthly, busy, loadOffer, checkout, purchase, restore, syncWithServer, manageTarget, manage, reset };
}
