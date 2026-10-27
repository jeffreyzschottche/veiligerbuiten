<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Billing;
use App\Services\Ops;
use App\Services\StoreBilling;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(private readonly Billing $billing, private readonly StoreBilling $store) {}

    /** Direct de actuele status: na een aankoop in de app (source=store) of na terugkomst van Stripe. */
    public function refresh(Request $request): JsonResponse
    {
        $request->validate(['session_id' => ['nullable', 'string', 'max:255'], 'source' => ['nullable', 'in:store,web']]);
        $user = $request->user();
        $user = $request->input('source') === 'store' || ($request->input('source') !== 'web' && in_array($user->billing_provider, StoreBilling::PROVIDERS, true))
            ? $this->store->refresh($user)
            : $this->billing->refresh($user, $request->input('session_id'));

        return response()->json(['data' => [
            'active' => $user->hasActiveSubscription(),
            'status' => $user->subscription_status,
            'expires_at' => $user->subscription_expires_at?->toIso8601String(),
            'provider' => $user->paidVia(),
        ]]);
    }

    /** De Stripe-betaalpagina. Loopt er al een abonnement, dan de beheerpagina: nooit twee keer betalen. */
    public function checkout(Request $request): JsonResponse
    {
        abort_unless($this->billing->configured(), 503, 'Betalen is nog niet ingesteld.');
        $user = $request->user();
        $this->notInStore($user);
        $url = $user->hasActiveSubscription() && $user->stripe_subscription_id
            ? $this->billing->portalUrl($user)
            : $this->billing->checkoutUrl($user);

        return response()->json(['url' => $url]);
    }

    public function portal(Request $request): JsonResponse
    {
        abort_unless($this->billing->configured(), 503, 'Betalen is nog niet ingesteld.');
        $this->notInStore($request->user());
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

    /** RevenueCat-webhook; beveiligd met de Authorization-header die in RevenueCat is ingesteld. */
    public function storeWebhook(Request $request): JsonResponse
    {
        $secret = (string) config('services.revenuecat.webhook_secret');
        $header = (string) $request->header('Authorization');
        if ($secret === '' || ! (hash_equals($secret, $header) || hash_equals('Bearer '.$secret, $header))) {
            Ops::record('payment.webhook_rejected', Ops::ERROR, 'RevenueCat-webhook geweigerd', 'De Authorization-header klopt niet met REVENUECAT_WEBHOOK_SECRET. Aankopen in de app worden niet verwerkt.', [], 'revenuecat');
            abort(401);
        }

        $this->store->handle((array) $request->input('event', []));

        return response()->json(['ok' => true]);
    }

    /** Een abonnement uit de App Store of Google Play beheer je daar; nooit een tweede via Stripe. */
    private function notInStore(User $user): void
    {
        $via = $user->paidVia();
        abort_if(in_array($via, StoreBilling::PROVIDERS, true), 409, $via === 'play_store'
            ? 'Je abonnement loopt via Google Play. Beheer of zeg het daar op.'
            : 'Je abonnement loopt via de App Store. Beheer of zeg het daar op.');
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
