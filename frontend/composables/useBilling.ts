import { Capacitor } from '@capacitor/core';

/**
 * Abonnement via Stripe: € 0,99 per maand, geen proefperiode. Registreren en betalen
 * gebeurt op veiligerbuiten.nl; de app logt alleen in. Daarom staat er in de app geen
 * betaalknop en geen link naar de betaalpagina (dat staan de winkels niet toe), alleen
 * de tekst dat je je account op de website aanmaakt.
 */
export function useBilling() {
  const busy = useState('billing-busy', () => false);
  /** Op de website kan betalen; in de app niet. */
  const available = !Capacitor.isNativePlatform();

  /** Naar de Stripe-betaalpagina (of de beheerpagina als er al een abonnement loopt). */
  async function checkout() {
    await go('/me/subscription/checkout');
  }

  /** Naar Stripe's eigen pagina: opzeggen, betaalmethode wijzigen, facturen. */
  async function manage() {
    await go('/me/subscription/portal');
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

  /** Terug van Stripe: meteen de status ophalen, zonder op de webhook te wachten. */
  async function syncWithServer(sessionId?: string) {
    const auth = useAuthStore();
    await useApi().post('/me/subscription/refresh', sessionId ? { session_id: sessionId } : {}).catch(() => undefined);
    await auth.refreshAccount();
    return auth.isSubscribed;
  }

  return { available, busy, checkout, manage, syncWithServer };
}
