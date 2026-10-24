<?php

namespace Tests\Feature;

use App\Models\OpsEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StripeBillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.stripe.secret' => 'sk_test', 'services.stripe.webhook_secret' => 'whsec_test', 'services.stripe.price' => 'price_1', 'app.frontend_url' => 'https://veiligerbuiten.nl']);
    }

    public function test_paying_on_the_website_unlocks_the_app_even_before_the_webhook_arrives(): void
    {
        $user = User::factory()->create(['locale' => 'nl']);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/incidents/sync?latitude=52.37&longitude=4.89')->assertStatus(402);

        Http::fake([
            'api.stripe.com/v1/checkout/sessions/cs_1' => Http::response(['id' => 'cs_1', 'client_reference_id' => (string) $user->id, 'customer' => 'cus_1', 'subscription' => 'sub_1']),
            'api.stripe.com/v1/checkout/sessions' => Http::response(['url' => 'https://checkout.stripe.com/c/pay/cs_1']),
            'api.stripe.com/v1/subscriptions/sub_1' => Http::response($this->stripeSubscription($user)),
        ]);

        $this->postJson('/api/v1/me/subscription/checkout')->assertOk()->assertJsonPath('url', 'https://checkout.stripe.com/c/pay/cs_1');
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.stripe.com/v1/checkout/sessions'
            && $request['mode'] === 'subscription' && $request['client_reference_id'] === (string) $user->id
            && $request['line_items'][0]['price'] === 'price_1' && $request['customer_email'] === $user->email
            && $request['success_url'] === 'https://veiligerbuiten.nl/aanmelden?betaald={CHECKOUT_SESSION_ID}');

        // Terug van Stripe: de website vraagt meteen om te verversen met het sessie-id.
        $this->postJson('/api/v1/me/subscription/refresh', ['session_id' => 'cs_1'])->assertOk()->assertJsonPath('data.active', true)->assertJsonPath('data.status', 'active');
        $this->assertSame('cus_1', $user->fresh()->stripe_customer_id);
        Sanctum::actingAs($user->fresh()); // een echt volgend verzoek laadt het account opnieuw
        $this->getJson('/api/v1/incidents/sync?latitude=52.37&longitude=4.89')->assertOk();
        $this->assertStringNotContainsString('cus_1', $this->getJson('/api/v1/me')->getContent(), 'Stripe ids stay on the server');
    }

    public function test_someone_elses_checkout_session_gives_nothing(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        Http::fake(['api.stripe.com/v1/checkout/sessions/cs_x' => Http::response(['id' => 'cs_x', 'client_reference_id' => '999', 'customer' => 'cus_9', 'subscription' => 'sub_9'])]);

        $this->postJson('/api/v1/me/subscription/refresh', ['session_id' => 'cs_x'])->assertOk()->assertJsonPath('data.active', false);
        $this->assertNull($user->fresh()->stripe_customer_id);
    }

    public function test_cancelling_runs_until_the_end_of_the_period_and_then_access_stops(): void
    {
        $user = User::factory()->create(['stripe_customer_id' => 'cus_1']);
        $end = now()->addDays(12)->startOfSecond();
        $states = [
            $this->stripeSubscription($user),
            $this->stripeSubscription($user, ['cancel_at_period_end' => true, 'cancel_at' => $end->getTimestamp(), 'cancellation_details' => ['feedback' => 'too_expensive']]),
            $this->stripeSubscription($user, ['status' => 'canceled']),
        ];
        Http::fake(['api.stripe.com/v1/subscriptions/sub_1' => Http::sequence()->push($states[0])->push($states[1])->push($states[2])]);

        $this->stripeWebhook('customer.subscription.created', ['id' => 'sub_1'])->assertOk();
        $this->assertSame('active', $user->fresh()->subscription_status);
        $this->assertTrue($user->fresh()->subscription_expires_at->gt(now()->addMonth()), 'a few days of grace for the renewal');

        $this->stripeWebhook('customer.subscription.updated', ['id' => 'sub_1'])->assertOk();
        $this->assertSame('cancelled', $user->fresh()->subscription_status);
        $this->assertTrue($user->fresh()->subscription_expires_at->equalTo($end), 'cancelled: exactly until the paid period ends');
        $this->assertTrue($user->fresh()->hasActiveSubscription());

        $this->stripeWebhook('customer.subscription.deleted', ['id' => 'sub_1'])->assertOk();
        $this->assertFalse($user->fresh()->hasActiveSubscription());

        $this->assertSame(['payment.new', 'payment.cancel', 'payment.expired'], OpsEvent::orderBy('id')->pluck('type')->all());
        $this->assertStringContainsString('reden: too expensive', OpsEvent::where('type', 'payment.cancel')->value('message'));
    }

    public function test_a_renewal_is_reported_and_an_unpaid_one_keeps_access_for_a_few_days(): void
    {
        $user = User::factory()->create(['stripe_customer_id' => 'cus_1', 'stripe_subscription_id' => 'sub_1', 'subscription_status' => 'active', 'subscription_expires_at' => now()->addDays(2)]);
        Http::fake(['api.stripe.com/v1/subscriptions/sub_1' => Http::sequence()
            ->push($this->stripeSubscription($user))
            ->push($this->stripeSubscription($user, ['status' => 'past_due', 'items' => ['data' => [['current_period_end' => now()->getTimestamp()]]]]))]);

        $this->stripeWebhook('invoice.paid', ['id' => 'in_1', 'billing_reason' => 'subscription_cycle', 'customer' => 'cus_1', 'subscription' => 'sub_1', 'amount_paid' => 99, 'currency' => 'eur'])->assertOk();
        $this->assertStringContainsString('EUR 0,99 via Stripe', OpsEvent::where('type', 'payment.renewal')->value('message'));

        $this->stripeWebhook('customer.subscription.updated', ['id' => 'sub_1'])->assertOk();
        $this->assertSame('billing_issue', $user->fresh()->subscription_status);
        $this->assertTrue($user->fresh()->hasActiveSubscription());
        $this->travel(4)->days();
        $this->assertFalse($user->fresh()->hasActiveSubscription());
    }

    public function test_deleting_an_account_also_stops_the_stripe_subscription(): void
    {
        $user = User::factory()->create(['stripe_customer_id' => 'cus_1', 'password' => 'geheim-wachtwoord']);
        Sanctum::actingAs($user);
        Http::fake(['api.stripe.com/v1/customers/cus_1' => Http::sequence()->push('', 500)->push(['deleted' => true])]);

        $this->deleteJson('/api/v1/profile', ['password' => 'geheim-wachtwoord'])->assertStatus(503);
        $this->assertNotNull($user->fresh(), 'Stripe unreachable: the account stays, nobody keeps paying for nothing');
        $this->assertSame('payment.forget_failed', OpsEvent::value('type'));

        $this->deleteJson('/api/v1/profile', ['password' => 'geheim-wachtwoord'])->assertOk();
        $this->assertNull($user->fresh());
        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE' && $request->url() === 'https://api.stripe.com/v1/customers/cus_1');
    }

    public function test_a_gift_gives_access_without_stripe(): void
    {
        $user = User::factory()->create(['email' => 'review@veiligerbuiten.nl']);
        $this->artisan('veiligbuiten:gift', ['email' => 'review@veiligerbuiten.nl', '--days' => 30])->assertSuccessful();
        $this->assertTrue($user->fresh()->hasActiveSubscription());
        $this->artisan('veiligbuiten:gift', ['email' => 'review@veiligerbuiten.nl', '--revoke' => true])->assertSuccessful();
        $this->assertFalse($user->fresh()->hasActiveSubscription());
    }
}
