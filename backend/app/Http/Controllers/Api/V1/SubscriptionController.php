<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Ops;
use App\Services\Billing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(private readonly Billing $billing) {}

    public function refresh(Request $request): JsonResponse
    {
        $user = $this->billing->refresh($request->user());

        return response()->json(['data' => [
            'active' => $user->hasActiveSubscription(),
            'status' => $user->subscription_status,
            'expires_at' => $user->subscription_expires_at?->toIso8601String(),
        ]]);
    }

    /** RevenueCat-webhook; beveiligd met de Authorization-header die in RevenueCat is ingesteld. */
    public function webhook(Request $request): JsonResponse
    {
        $secret = config('services.revenuecat.webhook_secret');
        if (! $secret || ! hash_equals('Bearer '.$secret, (string) $request->header('Authorization'))) {
            // Verkeerd ingesteld geheim = gemiste betalingen; dat wil je weten.
            Ops::record('payment.webhook_rejected', Ops::ERROR, 'RevenueCat-webhook geweigerd', 'De Authorization-header klopt niet met REVENUECAT_WEBHOOK_SECRET. Betalingen worden niet verwerkt.', [], 'auth');
            abort(401);
        }

        $this->billing->applyEvent((array) $request->input('event', []));

        return response()->json(['ok' => true]);
    }
}
