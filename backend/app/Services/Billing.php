<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Abonnement via Stripe: registreren en betalen op veiligerbuiten.nl, de app logt alleen in.
 *
 * Stripe stuurt events niet altijd op volgorde. Daarom gebruiken we een event alleen als
 * seintje en halen we het abonnement zelf opnieuw op: wat Stripe nú zegt, telt. Of er
 * iets veranderde (nieuw, opgezegd, verlopen), leiden we af uit de status die we al hadden.
 */
class Billing
{
    private const API = 'https://api.stripe.com/v1';

    /**
     * Een verlenging wordt pas zo'n uur na het einde van de periode afgeschreven, en een
     * mislukte betaling probeert Stripe nog een paar dagen opnieuw. Zolang loopt het door.
     */
    private const GRACE_DAYS = 3;

    public function configured(): bool
    {
        return filled(config('services.stripe.secret')) && filled(config('services.stripe.price'));
    }

    /** Een Stripe Checkout-pagina voor het maandabonnement; de website stuurt de bezoeker erheen. */
    public function checkoutUrl(User $user): string
    {
        $base = rtrim((string) config('app.frontend_url'), '/');
        $session = $this->stripe('post', '/checkout/sessions', [
            'mode' => 'subscription',
            'line_items' => [['price' => config('services.stripe.price'), 'quantity' => 1]],
            'client_reference_id' => (string) $user->id,
            'subscription_data' => ['metadata' => ['user_id' => (string) $user->id]],
            'success_url' => "{$base}/aanmelden?betaald={CHECKOUT_SESSION_ID}",
            'cancel_url' => "{$base}/aanmelden",
            'locale' => $user->locale === 'en' ? 'en' : 'nl',
            ...($user->stripe_customer_id ? ['customer' => $user->stripe_customer_id] : ['customer_email' => $user->email]),
        ]);

        return $session['url'];
    }

    /** Stripe's eigen pagina om op te zeggen, de betaalmethode te wijzigen of facturen te zien. */
    public function portalUrl(User $user): ?string
    {
        if (! $user->stripe_customer_id) {
            return null;
        }

        return $this->stripe('post', '/billing_portal/sessions', [
            'customer' => $user->stripe_customer_id,
            'return_url' => rtrim((string) config('app.frontend_url'), '/').'/account',
            'locale' => $user->locale === 'en' ? 'en' : 'nl',
        ])['url'];
    }

    /** Verwerkt een (al op echtheid gecontroleerd) Stripe-webhookevent. */
    public function handle(array $event): void
    {
        $object = $event['data']['object'] ?? [];
        match ($event['type'] ?? '') {
            'checkout.session.completed' => $this->completeCheckout($object),
            'customer.subscription.created', 'customer.subscription.updated', 'customer.subscription.deleted',
            'customer.subscription.paused', 'customer.subscription.resumed' => $this->sync($object['id'] ?? null),
            'invoice.paid' => $this->invoicePaid($object),
            default => null,
        };
    }

    /** Direct de actuele status, bijvoorbeeld als iemand net terugkomt van de betaalpagina. */
    public function refresh(User $user, ?string $checkoutSession = null): User
    {
        if (! $this->configured()) {
            return $user;
        }

        if ($checkoutSession) {
            $session = $this->stripe('get', '/checkout/sessions/'.rawurlencode($checkoutSession));
            // Alleen je eigen betaling: een gok naar andermans sessie-id levert niets op.
            if (($session['client_reference_id'] ?? null) === (string) $user->id) {
                $this->completeCheckout($session);
            }
        } elseif ($user->stripe_subscription_id) {
            $this->sync($user->stripe_subscription_id);
        } elseif ($user->stripe_customer_id) {
            $latest = $this->stripe('get', '/subscriptions', ['customer' => $user->stripe_customer_id, 'status' => 'all', 'limit' => 1])['data'][0] ?? null;
            if ($latest) $this->apply($user, $latest);
        }

        return $user->fresh();
    }

    /**
     * Bij het verwijderen van een account: de klant bij Stripe verwijderen. Dat stopt ook het
     * abonnement meteen, zodat niemand blijft betalen voor een account dat niet meer bestaat.
     */
    public function forget(User $user): void
    {
        if ($user->stripe_customer_id && $this->configured()) {
            $this->stripe('delete', '/customers/'.rawurlencode($user->stripe_customer_id), [], allowMissing: true);
        }
    }

    private function completeCheckout(array $session): void
    {
        $user = ctype_digit((string) ($session['client_reference_id'] ?? '')) ? User::find((int) $session['client_reference_id']) : null;
        if (! $user) {
            $this->unmatched('checkout.session.completed', $session['customer'] ?? null);

            return;
        }
        if (! empty($session['customer']) && $user->stripe_customer_id !== $session['customer']) {
            $user->forceFill(['stripe_customer_id' => $session['customer']])->save();
        }
        $subscription = $session['subscription'] ?? null;
        $this->sync(is_array($subscription) ? ($subscription['id'] ?? null) : $subscription);
    }

    private function invoicePaid(array $invoice): void
    {
        $subscription = $invoice['subscription'] ?? $invoice['parent']['subscription_details']['subscription'] ?? null;
        if (($invoice['billing_reason'] ?? null) !== 'subscription_cycle') {
            return; // de eerste betaling meldt de checkout al als nieuwe abonnee
        }
        $user = $this->userForCustomer($invoice['customer'] ?? null);
        if (! $user) {
            $this->unmatched('invoice.paid', $invoice['customer'] ?? null);

            return;
        }
        Ops::record('payment.renewal', Ops::INFO, 'Abonnement verlengd', "Account #{$user->id} · {$this->money($invoice['amount_paid'] ?? null, $invoice['currency'] ?? null)} via Stripe", [
            'account' => $user->id, 'amount' => ($invoice['amount_paid'] ?? 0) / 100, 'currency' => strtoupper((string) ($invoice['currency'] ?? '')),
        ], 'invoice|'.($invoice['id'] ?? ''));
        if (is_string($subscription)) $this->sync($subscription);
    }

    private function sync(?string $subscriptionId): void
    {
        if (! $subscriptionId) {
            return;
        }
        $subscription = $this->stripe('get', '/subscriptions/'.rawurlencode($subscriptionId));
        $user = $this->userFor($subscription);
        if (! $user) {
            $this->unmatched('subscription', $subscription['customer'] ?? null);

            return;
        }
        $this->apply($user, $subscription);
    }

    private function apply(User $user, array $subscription): void
    {
        $stripeStatus = $subscription['status'] ?? '';
        $current = $user->stripe_subscription_id === null || $user->stripe_subscription_id === $subscription['id'];
        $live = in_array($stripeStatus, ['active', 'trialing', 'past_due'], true);
        // Nog niet betaald (incomplete), of een oud abonnement terwijl er een nieuwer loopt: niets doen.
        if ($stripeStatus === 'incomplete' || (! $current && ! $live)) {
            return;
        }

        $periodEnd = $subscription['current_period_end'] ?? $subscription['items']['data'][0]['current_period_end'] ?? null;
        $periodEnd = $periodEnd ? Carbon::createFromTimestamp($periodEnd) : null;
        $cancelAt = ! empty($subscription['cancel_at']) ? Carbon::createFromTimestamp($subscription['cancel_at']) : null;
        $cancelling = ($subscription['cancel_at_period_end'] ?? false) || $cancelAt !== null;

        [$status, $expiresAt] = match (true) {
            $live && $stripeStatus === 'past_due' => ['billing_issue', $periodEnd?->copy()->addDays(self::GRACE_DAYS)],
            $live && $cancelling => ['cancelled', $cancelAt ?? $periodEnd],
            $live => ['active', $periodEnd?->copy()->addDays(self::GRACE_DAYS)],
            default => ['expired', now()], // canceled, unpaid, incomplete_expired, paused
        };

        $previous = $user->subscription_status;
        $user->forceFill([
            'subscription_status' => $status,
            'subscription_expires_at' => $expiresAt,
            'stripe_subscription_id' => $subscription['id'],
            'stripe_customer_id' => $user->stripe_customer_id ?? ($subscription['customer'] ?? null),
        ])->save();

        $this->report($user, $previous, $status, $subscription);
    }

    /** Belangrijke betaalmomenten naar het beheerlogboek; alleen het accountnummer, geen persoonsgegevens. */
    private function report(User $user, ?string $previous, string $status, array $subscription): void
    {
        [$type, $level, $title] = match (true) {
            $status === $previous => [null, null, null],
            $status === 'active' && $previous === 'cancelled' => ['payment.uncancel', Ops::INFO, 'Opzegging teruggedraaid'],
            $status === 'active' && $previous === 'billing_issue' => ['payment.recovered', Ops::INFO, 'Betaling alsnog gelukt'],
            $status === 'active' => ['payment.new', Ops::INFO, 'Nieuwe abonnee'],
            $status === 'cancelled' => ['payment.cancel', Ops::WARNING, 'Abonnement opgezegd'],
            $status === 'billing_issue' => ['payment.billing_issue', Ops::WARNING, 'Betaalprobleem'],
            $status === 'expired' && in_array($previous, ['active', 'cancelled', 'billing_issue'], true) => ['payment.expired', Ops::INFO, 'Abonnement verlopen'],
            default => [null, null, null],
        };
        if (! $type) {
            return;
        }
        $price = $subscription['items']['data'][0]['price'] ?? [];
        $reason = $subscription['cancellation_details']['feedback'] ?? $subscription['cancellation_details']['reason'] ?? null;
        Ops::record($type, $level, $title, "Account #{$user->id} · {$this->money($price['unit_amount'] ?? null, $price['currency'] ?? null)} via Stripe".($reason ? ' · reden: '.str_replace('_', ' ', $reason) : ''), [
            'account' => $user->id, 'amount' => isset($price['unit_amount']) ? $price['unit_amount'] / 100 : null,
            'currency' => strtoupper((string) ($price['currency'] ?? '')) ?: null, 'reason' => $reason,
        ]);
    }

    /** Er is betaald, maar we weten niet door wie: dat moet je zien. */
    private function unmatched(string $what, mixed $customer): void
    {
        Ops::record('payment.unmatched', Ops::ERROR, 'Betaling zonder gekoppeld account', "Stripe-{$what} voor klant ".mb_substr((string) ($customer ?? '?'), 0, 40).' hoort bij geen enkel account.', ['customer' => is_string($customer) ? $customer : null], (string) $customer);
    }

    private function money(?int $cents, ?string $currency): string
    {
        return $cents === null ? 'onbekend bedrag' : strtoupper((string) $currency).' '.number_format($cents / 100, 2, ',', '.');
    }

    private function userFor(array $subscription): ?User
    {
        $id = $subscription['metadata']['user_id'] ?? null;

        return (ctype_digit((string) $id) ? User::find((int) $id) : null) ?? $this->userForCustomer($subscription['customer'] ?? null);
    }

    private function userForCustomer(?string $customer): ?User
    {
        return $customer ? User::query()->where('stripe_customer_id', $customer)->first() : null;
    }

    private function stripe(string $method, string $path, array $data = [], bool $allowMissing = false): array
    {
        $response = Http::withToken((string) config('services.stripe.secret'))->asForm()->acceptJson()->timeout(10)
            ->{$method}(self::API.$path, $data);
        if ($allowMissing && $response->status() === 404) {
            return [];
        }

        return $response->throw()->json() ?? [];
    }
}
