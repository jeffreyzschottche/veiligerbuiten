<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Abonnement in de app, via RevenueCat (App Store en Google Play).
 *
 * De app logt in RevenueCat in met het account-id als app_user_id. Net als bij Stripe is een
 * webhookevent alleen een seintje: we halen de klant opnieuw op en wat RevenueCat nú zegt, telt.
 * Zo maakt de volgorde waarin events binnenkomen niet uit.
 */
class StoreBilling
{
    private const API = 'https://api.revenuecat.com/v1';

    /** Betaalkanalen die via RevenueCat lopen. */
    public const PROVIDERS = ['app_store', 'play_store', 'revenuecat'];

    public function configured(): bool
    {
        return filled(config('services.revenuecat.secret_key'));
    }

    /** Verwerkt een (al op echtheid gecontroleerd) RevenueCat-webhookevent. */
    public function handle(array $event): void
    {
        if (($event['type'] ?? '') === 'TEST') {
            return;
        }

        // Bij een overdracht (zelfde Apple-/Google-account, ander VeiligerBuiten-account) beide kanten bijwerken.
        $ids = ($event['type'] ?? '') === 'TRANSFER'
            ? [...($event['transferred_from'] ?? []), ...($event['transferred_to'] ?? [])]
            : [$event['app_user_id'] ?? null, ...($event['aliases'] ?? [])];
        $users = collect($ids)->map(fn ($id) => $this->userFor($id))->filter()->unique('id');

        if ($users->isEmpty()) {
            // Er is betaald, maar we weten niet door wie: dat moet je zien.
            Ops::record('payment.unmatched', Ops::ERROR, 'Betaling zonder gekoppeld account', 'RevenueCat-'.($event['type'] ?? '?').' voor app_user_id '.mb_substr((string) ($event['app_user_id'] ?? '?'), 0, 60).' hoort bij geen enkel account.', ['type' => $event['type'] ?? null, 'store' => $event['store'] ?? null], (string) ($event['app_user_id'] ?? '?'));

            return;
        }

        foreach ($users as $user) {
            $this->configured() ? $this->refresh($user) : $this->applyEvent($user, $event);
        }
    }

    /** Haalt de actuele status direct op, bijvoorbeeld meteen na een aankoop in de app. */
    public function refresh(User $user): User
    {
        if (! $this->configured()) {
            return $user;
        }

        $subscriber = $this->revenuecat('get', '/subscribers/'.rawurlencode((string) $user->id))['subscriber'] ?? [];
        $entitlement = $subscriber['entitlements'][config('services.revenuecat.entitlement')] ?? null;
        if (! $entitlement) {
            // Nooit iets gekocht in de app: een abonnement via de website blijft gewoon staan.
            if (in_array($user->billing_provider, self::PROVIDERS, true)) {
                $this->apply($user, 'expired', now(), $user->billing_provider);
            }

            return $user->fresh();
        }

        $product = $subscriber['subscriptions'][$entitlement['product_identifier'] ?? ''] ?? [];
        $expiresAt = $this->latest($entitlement['expires_date'] ?? null, $product['grace_period_expires_date'] ?? null);
        $status = match (true) {
            $expiresAt === null => 'active', // levenslang, bijvoorbeeld een cadeau uit RevenueCat zonder einddatum
            ! $expiresAt->isFuture() => 'expired',
            ! empty($product['billing_issues_detected_at']) => 'billing_issue',
            ! empty($product['unsubscribe_detected_at']) => 'cancelled',
            default => 'active',
        };

        $this->apply($user, $status, $expiresAt ?? now()->addYears(100), $this->provider($product['store'] ?? null));

        return $user->fresh();
    }

    /** Bij het verwijderen van een account: de klant bij RevenueCat wissen. Opzeggen kan alleen in de winkel zelf. */
    public function forget(User $user): void
    {
        if ($this->configured() && in_array($user->billing_provider, self::PROVIDERS, true)) {
            $this->revenuecat('delete', '/subscribers/'.rawurlencode((string) $user->id), allowMissing: true);
        }
    }

    /** Zonder geheime sleutel: het event zelf gebruiken. */
    private function applyEvent(User $user, array $event): void
    {
        $expiresAt = isset($event['expiration_at_ms']) ? Carbon::createFromTimestampMs($event['expiration_at_ms']) : null;
        $status = match ($event['type'] ?? '') {
            'INITIAL_PURCHASE', 'RENEWAL', 'PRODUCT_CHANGE', 'UNCANCELLATION', 'SUBSCRIPTION_EXTENDED', 'TEMPORARY_ENTITLEMENT_GRANT', 'NON_RENEWING_PURCHASE' => 'active',
            'CANCELLATION' => 'cancelled', // loopt door tot de verloopdatum
            'BILLING_ISSUE' => 'billing_issue',
            'EXPIRATION' => 'expired',
            default => null,
        };
        if ($status !== null) {
            $this->apply($user, $status, $status === 'expired' ? now() : ($expiresAt ?? $user->subscription_expires_at ?? now()), $this->provider($event['store'] ?? null));
        }
    }

    private function apply(User $user, string $status, Carbon $expiresAt, string $provider): void
    {
        if (! $user->acceptsBillingFrom($provider, $status !== 'expired', $expiresAt)) {
            // Er loopt al een abonnement via de website of een andere winkel: dat wint.
            if ($status !== 'expired' && $user->paidVia() !== null) {
                Billing::doubleSubscription($user, $provider);
            }

            return;
        }

        $previous = $user->subscription_status;
        $user->forceFill([
            'subscription_status' => $status,
            'subscription_expires_at' => $expiresAt,
            'billing_provider' => $provider,
        ])->save();

        Billing::report($user, $previous, $status, $this->label($provider));
    }

    private function provider(?string $store): string
    {
        return match (strtoupper((string) $store)) {
            'APP_STORE', 'MAC_APP_STORE' => 'app_store',
            'PLAY_STORE' => 'play_store',
            default => 'revenuecat',
        };
    }

    private function label(string $provider): string
    {
        return ['app_store' => 'via de App Store', 'play_store' => 'via Google Play'][$provider] ?? 'via RevenueCat';
    }

    private function latest(?string ...$dates): ?Carbon
    {
        return collect($dates)->filter()->map(fn (string $date) => Carbon::parse($date))->sortDesc()->first();
    }

    private function userFor(mixed $appUserId): ?User
    {
        return is_string($appUserId) && ctype_digit($appUserId) ? User::find((int) $appUserId) : null;
    }

    private function revenuecat(string $method, string $path, bool $allowMissing = false): array
    {
        $response = Http::withToken((string) config('services.revenuecat.secret_key'))->acceptJson()->timeout(8)
            ->{$method}(self::API.$path);
        if ($allowMissing && $response->status() === 404) {
            return [];
        }

        return $response->throw()->json() ?? [];
    }
}
