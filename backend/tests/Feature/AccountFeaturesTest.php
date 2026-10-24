<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\Installation;
use App\Models\User;
use App\Services\ReportQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private function report(Installation $installation, array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/reports', [
            'category' => 'verkeer', 'subtype' => 'Verkeersongeval',
            'latitude' => 52.3672, 'longitude' => 4.8952,
            'user_latitude' => 52.3702, 'user_longitude' => 4.8952, 'location_accuracy_m' => 15,
            ...$overrides,
        ], ['X-Installation-Token' => $installation->token]);
    }

    public function test_an_account_can_post_three_reports_per_day_and_not_a_fourth(): void
    {
        $user = $this->actingAsSubscriber();
        $installation = Installation::create(['user_id' => $user->id, 'token' => str_repeat('r', 64), 'trust_score' => 50]);

        $this->report($installation, ['latitude' => 52.3702])->assertCreated()->assertJsonPath('quota.remaining', 2);
        $this->report($installation, ['latitude' => 52.3762])->assertCreated()->assertJsonPath('quota.remaining', 1);
        $this->report($installation, ['latitude' => 52.3732, 'longitude' => 4.9032])->assertCreated()->assertJsonPath('quota.remaining', 0);
        $this->report($installation)->assertStatus(429)->assertJsonPath('code', 'report_quota_exceeded');

        $this->getJson('/api/v1/reports/quota')->assertOk()->assertJsonPath('data.remaining', 0);

        // Na middernacht (Amsterdamse tijd) mag het weer.
        $this->travelTo(now('Europe/Amsterdam')->addDay()->startOfDay()->addMinute()->utc());
        $this->assertSame(3, app(ReportQuota::class)->remaining($user));
    }

    public function test_reports_require_an_active_subscription(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $installation = Installation::create(['token' => str_repeat('s', 64), 'trust_score' => 50]);

        $this->report($installation)->assertStatus(402)->assertJsonPath('code', 'subscription_required');
    }

    public function test_places_are_private_and_limited(): void
    {
        $user = $this->actingAsSubscriber();
        $this->postJson('/api/v1/places', ['name' => 'Thuis', 'latitude' => 52.37, 'longitude' => 4.89, 'radius_m' => 800])->assertCreated();
        $this->getJson('/api/v1/places')->assertOk()->assertJsonCount(1, 'data');

        $other = User::factory()->create()->places()->create(['name' => 'Ander', 'latitude' => 52.0, 'longitude' => 5.0]);
        $this->putJson("/api/v1/places/{$other->id}", ['radius_m' => 200])->assertNotFound();
        $this->postJson('/api/v1/places', ['name' => 'Groot', 'latitude' => 52.37, 'longitude' => 4.89, 'radius_m' => 50000])->assertUnprocessable();

        for ($i = 1; $i < 10; $i++) {
            $user->places()->create(['name' => "Plek {$i}", 'latitude' => 52.37, 'longitude' => 4.89]);
        }
        $this->postJson('/api/v1/places', ['name' => 'Elfde', 'latitude' => 52.37, 'longitude' => 4.89])->assertUnprocessable();
    }

    public function test_settings_are_validated_and_returned_with_the_account(): void
    {
        $this->actingAsSubscriber();

        $this->putJson('/api/v1/me/settings', ['locale' => 'en', 'check_interval_minutes' => 15, 'alert_filters' => ['min_level' => 'danger']])
            ->assertOk()
            ->assertJsonPath('data.locale', 'en')
            ->assertJsonPath('data.settings.check_interval_minutes', 15)
            ->assertJsonPath('data.settings.alert_filters.min_level', 'danger')
            ->assertJsonPath('data.report_quota.limit', 3);

        $this->putJson('/api/v1/me/settings', ['check_interval_minutes' => 1])->assertUnprocessable();
    }

    public function test_sync_returns_everything_within_thirty_km_with_level_and_reach(): void
    {
        $this->actingAsSubscriber();
        $base = ['category' => 'brand', 'subtype' => 'Brand', 'status' => 'officieel', 'visibility' => 'public', 'source' => 'p2000_zwaailicht', 'severity' => 4, 'confidence' => 1, 'incident_type' => 'event', 'expires_at' => now()->addHour(), 'last_activity_at' => now()];
        Incident::create([...$base, 'latitude' => 52.37, 'longitude' => 4.89]);
        Incident::create([...$base, 'latitude' => 52.58, 'longitude' => 4.89]); // ~23 km
        Incident::create([...$base, 'latitude' => 52.70, 'longitude' => 4.89]); // ~37 km

        $this->getJson('/api/v1/incidents/sync?latitude=52.37&longitude=4.89')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.level', 'danger')
            ->assertJsonPath('data.0.sourceName', 'Zwaailicht.nl')
            ->assertJsonPath('mode', 'full')
            // Gevaar in de buurt: vaker verversen, maar nooit onder de ondergrens.
            ->assertJsonPath('next_sync_after', 900);
    }

    public function test_the_mcp_server_answers_tool_calls_for_the_token_owner(): void
    {
        $user = User::factory()->create(['subscription_expires_at' => now()->addMonth()]);
        $token = $user->rotateAiToken();
        $user->places()->create(['name' => 'Thuis', 'latitude' => 52.3702, 'longitude' => 4.8952, 'radius_m' => 1000]);
        Incident::create(['category' => 'onveilig', 'subtype' => 'Bommelding', 'status' => 'officieel', 'visibility' => 'public', 'source' => 'politie', 'latitude' => 52.3712, 'longitude' => 4.8952, 'severity' => 5, 'confidence' => 1, 'incident_type' => 'event', 'expires_at' => now()->addHour(), 'last_activity_at' => now()]);

        $this->postJson('/mcp/vb_wrong', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertUnauthorized();

        $this->postJson("/mcp/{$token}", ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-06-18']])
            ->assertOk()->assertJsonPath('result.serverInfo.name', 'veiligerbuiten');
        $this->postJson("/mcp/{$token}", ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'])->assertStatus(202);
        $this->postJson("/mcp/{$token}", ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'])
            ->assertOk()->assertJsonPath('result.tools.0.name', 'get_situations_nearby');

        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => ['name' => 'get_situations_nearby', 'arguments' => ['place' => 'thuis']]], ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.situations.0.title', 'Bommelding');
    }
}
