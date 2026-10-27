<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Tests praten nooit met echte bronnen; elk verzoek dat niet is nagebootst faalt meteen.
        Http::preventStrayRequests();
    }

    /** Een ingelogde gebruiker met een actief abonnement. */
    protected function actingAsSubscriber(array $attributes = []): User
    {
        $user = User::factory()->create(['subscription_status' => 'active', 'subscription_expires_at' => now()->addMonth(), ...$attributes]);
        Sanctum::actingAs($user);

        return $user;
    }

    /** Een Stripe-webhook met een geldige handtekening, zoals Stripe hem stuurt. */
    protected function stripeWebhook(string $type, array $object, ?string $secret = null): \Illuminate\Testing\TestResponse
    {
        $body = json_encode(['id' => 'evt_'.uniqid(), 'type' => $type, 'data' => ['object' => $object]]);
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$body}", $secret ?? (string) config('services.stripe.webhook_secret'));

        return $this->call('POST', '/api/v1/webhooks/stripe', [], [], [], ['HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}", 'CONTENT_TYPE' => 'application/json'], $body);
    }

    /** Een Stripe-abonnement zoals de API het teruggeeft (prijsperiode op het item, API 2025+). */
    protected function stripeSubscription(User $user, array $overrides = []): array
    {
        return array_replace_recursive([
            'id' => 'sub_1', 'customer' => 'cus_1', 'status' => 'active', 'cancel_at_period_end' => false, 'cancel_at' => null,
            'metadata' => ['user_id' => (string) $user->id],
            'items' => ['data' => [['current_period_end' => now()->addMonth()->getTimestamp(), 'price' => ['id' => 'price_1', 'unit_amount' => 99, 'currency' => 'eur']]]],
        ], $overrides);
    }
}
