import { Capacitor } from '@capacitor/core';
import { Purchases, type PurchasesPackage } from '@revenuecat/purchases-capacitor';

/**
 * Abonnement via RevenueCat: € 0,99 per maand, geen proefperiode. RevenueCat
 * meldt aankopen ook via de webhook aan de server; na een aankoop vragen we
 * de server direct om te verversen, zodat de app niet op de webhook wacht.
 */
export function useBilling() {
  const configuredFor = useState<number | null>('billing-user', () => null);
  const monthly = useState<PurchasesPackage | null>('billing-package', () => null);
  const busy = useState('billing-busy', () => false);
  const available = Capacitor.isNativePlatform();

  async function configure() {
    const auth = useAuthStore();
    if (!available || !auth.account) return false;
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

  async function loadOffer() {
    if (!(await configure().catch(() => false))) return null;
    const offerings = await Purchases.getOfferings().catch(() => null);
    monthly.value = offerings?.current?.monthly ?? offerings?.current?.availablePackages[0] ?? null;
    return monthly.value;
  }

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

  async function syncWithServer() {
    const auth = useAuthStore();
    await useApi().post('/me/subscription/refresh').catch(() => undefined);
    await auth.refreshAccount();
    return auth.isSubscribed;
  }

  async function manage() {
    const url = Capacitor.getPlatform() === 'ios'
      ? 'https://apps.apple.com/account/subscriptions'
      : 'https://play.google.com/store/account/subscriptions?package=nl.veiligerbuiten.app';
    window.open(url, '_system');
  }

  function reset() {
    if (configuredFor.value !== null && available) Purchases.logOut().catch(() => undefined);
    configuredFor.value = null;
    monthly.value = null;
  }

  return { available, monthly, busy, loadOffer, purchase, restore, manage, reset, syncWithServer };
}
