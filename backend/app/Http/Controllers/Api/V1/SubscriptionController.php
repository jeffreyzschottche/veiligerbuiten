<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Billing;
use App\Services\Ops;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(private readonly Billing $billing) {}

    public function refresh(Request $request): JsonResponse
    {
        $request->validate(['session_id' => ['nullable', 'string', 'max:255']]);
        $user = $this->billing->refresh($request->user(), $request->input('session_id'));

        return response()->json(['data' => [
            'active' => $user->hasActiveSubscription(),
            'status' => $user->subscription_status,
            'expires_at' => $user->subscription_expires_at?->toIso8601String(),
        ]]);
    }

    /** De Stripe-betaalpagina. Loopt er al een abonnement, dan de beheerpagina: nooit twee keer betalen. */
    public function checkout(Request $request): JsonResponse
    {
        abort_unless($this->billing->configured(), 503, 'Betalen is nog niet ingesteld.');
        $user = $request->user();
        $url = $user->hasActiveSubscription() && $user->stripe_subscription_id
            ? $this->billing->portalUrl($user)
            : $this->billing->checkoutUrl($user);

        return response()->json(['url' => $url]);
    }

    public function portal(Request $request): JsonResponse
    {
        abort_unless($this->billing->configured(), 503, 'Betalen is nog niet ingesteld.');
        $url = $this->billing->portalUrl($request->user());
        abort_unless($url !== null, 404, 'Er is nog geen abonnement om te beheren.');

        return response()->json(['url' => $url]);
    }

    /** Stripe-webhook; alleen events met een geldige Stripe-Signature tellen. */
    public function webhook(Request $request): JsonResponse
    {
        $event = $this->verified($request);
        if ($event === null) {
            // Verkeerd ingesteld geheim = gemiste betalingen; dat wil je weten.
            Ops::record('payment.webhook_rejected', Ops::ERROR, 'Stripe-webhook geweigerd', 'De handtekening klopt niet met STRIPE_WEBHOOK_SECRET. Betalingen worden niet verwerkt.', [], 'auth');
            abort(400);
        }

        $this->billing->handle($event);

        return response()->json(['ok' => true]);
    }

    /** Stripe's handtekening: HMAC-SHA256 over "tijdstip.body", hoogstens 5 minuten oud. */
    private function verified(Request $request): ?array
    {
        $secret = (string) config('services.stripe.webhook_secret');
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', (string) $request->header('Stripe-Signature')) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($key === 't') $timestamp = (int) $value;
            if ($key === 'v1') $signatures[] = $value;
        }
        if ($secret === '' || ! $timestamp || abs(time() - $timestamp) > 300) {
            return null;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return json_decode($request->getContent(), true) ?: null;
            }
        }

        return null;
    }
}
