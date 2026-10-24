<?php

namespace App\Services;

use App\Models\User;
use App\Services\Ops;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Abonnementsstatus via RevenueCat (App Store + Google Play).
 * De app logt in RevenueCat in met het account-id als app_user_id.
 */
class Billing
{
    /** Verwerkt een RevenueCat-webhookevent. */
    public function applyEvent(array $event): ?User
    {
        $user = $this->userFor($event['app_user_id'] ?? null)
            ?? collect($event['aliases'] ?? [])->map(fn ($alias) => $this->userFor($alias))->filter()->first();
        if (! $user) {
            // Er is betaald, maar we weten niet door wie: dat moet je zien.
            Ops::record('payment.unmatched', Ops::ERROR, 'Betaling zonder gekoppeld account', "RevenueCat-event {$this->describe($event)} voor app_user_id ".mb_substr((string) ($event['app_user_id'] ?? '?'), 0, 60).'.', ['type' => $event['type'] ?? null, 'store' => $event['store'] ?? null]);

            return null;
        }

        $expiresAt = isset($event['expiration_at_ms']) ? Carbon::createFromTimestampMs($event['expiration_at_ms']) : null;
        $status = match ($event['type'] ?? '') {
            'INITIAL_PURCHASE', 'RENEWAL', 'PRODUCT_CHANGE', 'UNCANCELLATION', 'SUBSCRIPTION_EXTENDED', 'TEMPORARY_ENTITLEMENT_GRANT' => 'active',
            'CANCELLATION' => 'cancelled', // loopt door tot de verloopdatum
            'BILLING_ISSUE' => 'billing_issue',
            'EXPIRATION' => 'expired',
            default => null,
        };
        if ($status === null) {
            return $user;
        }

        $user->forceFill([
            'subscription_status' => $status,
            'subscription_expires_at' => $status === 'expired' ? now() : ($expiresAt ?? $user->subscription_expires_at),
        ])->save();
        $this->report($event, $user);

        return $user;
    }

    /** Belangrijke betaalmomenten naar het beheerlogboek; alleen het accountnummer, geen persoonsgegevens. */
    private function report(array $event, User $user): void
    {
        [$type, $level, $title] = match ($event['type'] ?? '') {
            'INITIAL_PURCHASE' => ['payment.new', Ops::INFO, 'Nieuwe abonnee'],
            'RENEWAL' => ['payment.renewal', Ops::INFO, 'Abonnement verlengd'],
            'UNCANCELLATION' => ['payment.uncancel', Ops::INFO, 'Opzegging teruggedraaid'],
            'CANCELLATION' => ['payment.cancel', Ops::WARNING, 'Abonnement opgezegd'],
            'BILLING_ISSUE' => ['payment.billing_issue', Ops::WARNING, 'Betaalprobleem'],
            'EXPIRATION' => ['payment.expired', Ops::INFO, 'Abonnement verlopen'],
            default => [null, null, null],
        };
        if ($type) {
            Ops::record($type, $level, $title, "Account #{$user->id} · {$this->describe($event)}", [
                'account' => $user->id, 'store' => $event['store'] ?? null, 'price' => $event['price'] ?? null,
                'currency' => $event['currency'] ?? null, 'reason' => $event['cancel_reason'] ?? null,
            ]);
        }
    }

    private function describe(array $event): string
    {
        $price = isset($event['price']) ? ' · '.($event['currency'] ?? '').' '.number_format((float) $event['price'], 2, ',', '.') : '';
        $store = ['APP_STORE' => 'App Store', 'PLAY_STORE' => 'Google Play'][$event['store'] ?? ''] ?? ($event['store'] ?? 'onbekende winkel');
        $reason = isset($event['cancel_reason']) ? ' · reden: '.mb_strtolower((string) $event['cancel_reason']) : '';

        return ($event['type'] ?? '?')." via {$store}{$price}{$reason}";
    }

    /** Haalt de actuele status direct op, bijvoorbeeld meteen na een aankoop in de app. */
    public function refresh(User $user): User
    {
        $key = config('services.revenuecat.secret_key');
        if (! $key) {
            return $user;
        }

        $subscriber = Http::withToken($key)->acceptJson()->timeout(8)
            ->get('https://api.revenuecat.com/v1/subscribers/'.urlencode((string) $user->id))
            ->throw()->json('subscriber');
        $entitlement = $subscriber['entitlements'][config('services.revenuecat.entitlement')] ?? null;
        $expiresAt = isset($entitlement['expires_date']) ? Carbon::parse($entitlement['expires_date']) : null;

        $user->forceFill([
            'subscription_status' => $expiresAt?->isFuture() ? 'active' : ($entitlement ? 'expired' : 'none'),
            'subscription_expires_at' => $expiresAt,
        ])->save();

        return $user;
    }

    private function userFor(?string $appUserId): ?User
    {
        return $appUserId !== null && ctype_digit($appUserId) ? User::find((int) $appUserId) : null;
    }
}
