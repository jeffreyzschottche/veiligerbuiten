<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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
        abort_unless($secret && hash_equals('Bearer '.$secret, (string) $request->header('Authorization')), 401);

        $this->billing->applyEvent((array) $request->input('event', []));

        return response()->json(['ok' => true]);
    }
}
