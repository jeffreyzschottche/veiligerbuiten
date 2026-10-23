<?php

namespace App\Services;

use App\Models\User;
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

        return $user;
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
