<?php

namespace Tests\Feature;

use App\Models\OpsEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StoreBillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.revenuecat.secret_key' => 'sk_rc', 'services.revenuecat.webhook_secret' => 'rc_secret', 'services.revenuecat.entitlement' => 'premium',
            'services.stripe.secret' => 'sk_test', 'services.stripe.webhook_secret' => 'whsec_test', 'services.stripe.price' => 'price_1',
            'app.frontend_url' => 'https://veiligerbuiten.nl',
        ]);
    }

    public function test_a_purchase_in_the_app_unlocks_the_account_right_away(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/incidents/sync?latitude=52.37&longitude=4.89')->assertStatus(402);

        Http::fake(["api.revenuecat.com/v1/subscribers/{$user->id}" => Http::response($this->subscriber('app_store'))]);
        $this->postJson('/api/v1/me/subscription/refresh', ['source' => 'store'])->assertOk()
            ->assertJsonPath('data.active', true)->assertJsonPath('data.status', 'active')->assertJsonPath('data.provider', 'app_store');

        Sanctum::actingAs($user->fresh());
        $this->getJson('/api/v1/incidents/sync?latitude=52.37&longitude=4.89')->assertOk();
        $this->getJson('/api/v1/me')->assertJsonPath('data.subscription.provider', 'app_store');
    }

    public function test_the_webhook_needs_the_configured_authorization_header(): void
    {
        $user = User::factory()->create();
        $this->postJson('/api/v1/webhooks/revenuecat', ['event' => ['type' => 'INITIAL_PURCHASE', 'app_user_id' => (string) $user->id]], ['Authorization' => 'wrong'])->assertUnauthorized();
        $this->assertFalse($user->fresh()->hasActiveSubscription());
        $this->assertSame(1, OpsEvent::where('type', 'payment.webhook_rejected')->count());
    }

    public function test_webhook_events_follow_what_revenuecat_says_now(): void
    {
        $user = User::factory()->create();
        $end = now()->addDays(20)->startOfSecond();
        Http::fake(["api.revenuecat.com/v1/subscribers/{$user->id}" => Http::sequence()
            ->push($this->subscriber('play_store'))
            ->push($this->subscriber('play_store', ['expires_date' => $end->toIso8601String(), 'unsubscribe_detected_at' => now()->toIso8601String()]))
            ->push($this->subscriber('play_store', ['expires_date' => now()->subMinute()->toIso8601String()]))]);

        $this->storeWebhook(['type' => 'INITIAL_PURCHASE', 'app_user_id' => (string) $user->id])->assertOk();
        $this->assertSame('active', $user->fresh()->subscription_status);
        $this->assertSame('play_store', $user->fresh()->billing_provider);

        $this->storeWebhook(['type' => 'CANCELLATION', 'app_user_id' => (string) $user->id])->assertOk();
        $this->assertSame('cancelled', $user->fresh()->subscription_status);
        $this->assertTrue($user->fresh()->subscription_expires_at->equalTo($end));
        $this->assertTrue($user->fresh()->hasActiveSubscription(), 'runs until the paid period ends');

        $this->storeWebhook(['type' => 'EXPIRATION', 'app_user_id' => (string) $user->id])->assertOk();
        $this->assertFalse($user->fresh()->hasActiveSubscription());
        $this->assertSame(['payment.new', 'payment.cancel', 'payment.expired'], OpsEvent::orderBy('id')->pluck('type')->all());
    }

    public function test_an_active_website_subscription_is_not_overwritten_by_the_store(): void
    {
        $expiresAt = now()->addDays(25)->startOfSecond();
        $user = User::factory()->create(['stripe_customer_id' => 'cus_1', 'stripe_subscription_id' => 'sub_1', 'subscription_status' => 'active', 'subscription_expires_at' => $expiresAt, 'billing_provider' => 'stripe']);
        Sanctum::actingAs($user);

        // Ooit in de app gekocht en al lang verlopen: de website blijft gelden.
        Http::fake(["api.revenuecat.com/v1/subscribers/{$user->id}" => Http::response($this->subscriber('app_store', ['expires_date' => now()->subMonths(2)->toIso8601String()]))]);
        $this->postJson('/api/v1/me/subscription/refresh', ['source' => 'store'])->assertOk()->assertJsonPath('data.active', true)->assertJsonPath('data.provider', 'stripe');
        $this->assertSame('stripe', $user->fresh()->billing_provider);
        $this->assertTrue($user->fresh()->subscription_expires_at->equalTo($expiresAt));
    }

    public function test_an_active_store_subscription_blocks_a_second_one_on_the_website(): void
    {
        $user = User::factory()->create(['subscription_status' => 'active', 'subscription_expires_at' => now()->addDays(25), 'billing_provider' => 'app_store']);
        Sanctum::actingAs($user);
        Http::fake();

        $this->postJson('/api/v1/me/subscription/checkout')->assertStatus(409);
        $this->postJson('/api/v1/me/subscription/portal')->assertStatus(409);
        Http::assertNothingSent();

        // En een Stripe-einde (bijvoorbeeld een oud, opgezegd webabonnement) laat het winkelabonnement staan.
        Http::fake(['api.stripe.com/v1/subscriptions/sub_old' => Http::response($this->stripeSubscription($user, ['id' => 'sub_old', 'status' => 'canceled']))]);
        $this->stripeWebhook('customer.subscription.deleted', ['id' => 'sub_old'])->assertOk();
        $this->assertSame('app_store', $user->fresh()->billing_provider);
        $this->assertTrue($user->fresh()->hasActiveSubscription());
    }

    public function test_a_store_purchase_takes_over_from_a_gift(): void
    {
        $user = User::factory()->create(['subscription_status' => 'gift', 'subscription_expires_at' => now()->addDays(300)]);
        Http::fake(["api.revenuecat.com/v1/subscribers/{$user->id}" => Http::response($this->subscriber('app_store'))]);

        $this->storeWebhook(['type' => 'INITIAL_PURCHASE', 'app_user_id' => (string) $user->id])->assertOk();
        $this->assertSame('active', $user->fresh()->subscription_status);
        $this->assertSame('app_store', $user->fresh()->paidVia());
    }

    public function test_deleting_the_account_removes_the_revenuecat_customer(): void
    {
        $user = User::factory()->create(['password' => 'een-lang-testwachtwoord', 'subscription_status' => 'active', 'subscription_expires_at' => now()->addDays(25), 'billing_provider' => 'play_store']);
        Sanctum::actingAs($user);
        Http::fake(["api.revenuecat.com/v1/subscribers/{$user->id}" => Http::response(['app_user_id' => (string) $user->id])]);

        $this->deleteJson('/api/v1/profile', ['password' => 'een-lang-testwachtwoord'])->assertSuccessful();
        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_ends_with($request->url(), "/subscribers/{$user->id}"));
        $this->assertNull(User::find($user->id));
    }

    private function storeWebhook(array $event): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/webhooks/revenuecat', ['api_version' => '1.0', 'event' => ['id' => uniqid(), 'store' => 'APP_STORE', ...$event]], ['Authorization' => 'Bearer rc_secret']);
    }

    /** Een RevenueCat-klant zoals GET /v1/subscribers/{id} hem teruggeeft. */
    private function subscriber(string $store, array $product = []): array
    {
        $product = ['expires_date' => now()->addMonth()->toIso8601String(), 'store' => $store, 'unsubscribe_detected_at' => null, 'billing_issues_detected_at' => null, ...$product];

        return ['subscriber' => [
            'entitlements' => ['premium' => ['expires_date' => $product['expires_date'], 'product_identifier' => 'nl.veiligerbuiten.app.monthly']],
            'subscriptions' => ['nl.veiligerbuiten.app.monthly' => $product],
        ]];
    }
}
