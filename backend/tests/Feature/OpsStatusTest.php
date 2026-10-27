<?php

namespace Tests\Feature;

use App\Models\OpsEvent;
use App\Models\User;
use App\Services\ScrapePlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpsStatusTest extends TestCase
{
    use RefreshDatabase;

    private function opsStatus(array $query = []): \Illuminate\Testing\TestResponse
    {
        return $this->getJson('/api/v1/ops/status?'.http_build_query($query), ['Authorization' => 'Bearer geheim']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.ops.token' => 'geheim', 'services.stripe.webhook_secret' => 'whsec_test', 'services.stripe.secret' => 'sk_test', 'services.stripe.price' => 'price_1']);
    }

    public function test_the_endpoint_does_not_exist_without_a_token_and_refuses_a_wrong_one(): void
    {
        config(['services.ops.token' => null]);
        $this->getJson('/api/v1/ops/status')->assertNotFound();
        config(['services.ops.token' => 'geheim']);
        $this->getJson('/api/v1/ops/status', ['Authorization' => 'Bearer fout'])->assertUnauthorized();
    }

    public function test_a_payment_shows_up_as_an_event_without_personal_data_and_only_once(): void
    {
        $user = User::factory()->create(['email' => 'iemand@example.nl', 'name' => 'Iemand']);
        Http::fake(['api.stripe.com/v1/subscriptions/sub_1' => Http::response($this->stripeSubscription($user))]);
        $this->stripeWebhook('customer.subscription.created', ['id' => 'sub_1'])->assertOk();
        $this->stripeWebhook('customer.subscription.updated', ['id' => 'sub_1'])->assertOk(); // dubbel of later event: geen tweede melding

        $first = $this->opsStatus()->assertOk()->assertJsonPath('events.0.type', 'payment.new')->assertJsonPath('events.0.title', 'Nieuwe abonnee')->assertJsonPath('metrics.subscribers_active', 1);
        $this->assertStringContainsString("Account #{$user->id}", $first->json('events.0.message'));
        $this->assertStringContainsString('EUR 0,99', $first->json('events.0.message'));
        $this->assertStringNotContainsString('iemand@example.nl', $first->getContent());
        $this->assertStringNotContainsString('Iemand', $first->getContent());

        $this->opsStatus(['since' => $first->json('cursor')])->assertJsonCount(0, 'events');
    }

    public function test_money_without_an_account_and_a_wrong_webhook_secret_are_errors(): void
    {
        Http::fake(['api.stripe.com/v1/subscriptions/sub_missing' => Http::response([
            'id' => 'sub_missing', 'customer' => 'cus_onbekend', 'metadata' => [], 'items' => ['data' => [['price' => ['id' => 'price_1']]]],
        ])]);
        $this->stripeWebhook('invoice.paid', ['id' => 'in_1', 'billing_reason' => 'subscription_cycle', 'subscription' => 'sub_missing', 'customer' => 'cus_onbekend', 'amount_paid' => 99, 'currency' => 'eur'])->assertOk();
        $this->stripeWebhook('invoice.paid', ['id' => 'in_2'], 'whsec_fout')->assertStatus(400);
        $this->stripeWebhook('invoice.paid', ['id' => 'in_3'], 'whsec_fout')->assertStatus(400);

        $this->assertSame(['payment.unmatched', 'payment.webhook_rejected'], OpsEvent::orderBy('id')->pluck('type')->all());
        $this->assertSame(2, OpsEvent::where('type', 'payment.webhook_rejected')->value('occurrences'), 'the same problem counts up instead of repeating');
        $this->opsStatus()->assertJsonPath('checks.4.key', 'errors')->assertJsonPath('checks.4.status', 'warning');
    }

    public function test_a_stopped_scheduler_and_a_failing_source_are_reported(): void
    {
        $this->opsStatus()->assertJsonPath('status', 'failed')->assertJsonPath('checks.1.key', 'scheduler')->assertJsonPath('checks.1.status', 'failed');

        Artisan::command('test:broken', fn () => 1);
        Artisan::command('test:fixed', fn () => 0);
        config(['veiligbuiten.scrape.sources' => ['nl_alert' => ['lane' => 'fast', 'command' => 'test:broken', 'min' => 2, 'max' => 2, 'night' => false]]]);
        $planner = app(ScrapePlanner::class);
        foreach (range(1, 4) as $i) $planner->run('fast', force: true);

        $this->assertSame(1, OpsEvent::where('type', 'source.failing')->count(), 'one warning after three failures, not one per failure');
        $this->opsStatus()->assertJsonPath('checks.1.status', 'ok')->assertJsonPath('checks.2.status', 'failed')->assertJsonPath('status', 'failed');

        config(['veiligbuiten.scrape.sources.nl_alert.command' => 'test:fixed']);
        $planner->run('fast', force: true);
        $this->assertSame(1, OpsEvent::where('type', 'source.recovered')->count());
        $this->opsStatus()->assertJsonPath('checks.2.status', 'ok');
    }

    public function test_an_unexpected_error_lands_in_the_log(): void
    {
        foreach (range(1, 2) as $i) report(new \RuntimeException('Iets onverwachts'));

        $event = OpsEvent::sole();
        $this->assertSame('app.error', $event->type);
        $this->assertSame(2, $event->occurrences);
    }
}
