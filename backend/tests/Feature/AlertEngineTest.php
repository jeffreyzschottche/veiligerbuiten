<?php

namespace Tests\Feature;

use App\Models\AlertDelivery;
use App\Models\Incident;
use App\Models\Installation;
use App\Models\User;
use App\Services\AlertEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class AlertEngineTest extends TestCase
{
    use RefreshDatabase;

    private function subscriber(array $attributes = []): User
    {
        $user = User::factory()->create(['subscription_expires_at' => now()->addMonth(), ...$attributes]);
        Installation::create(['user_id' => $user->id, 'token' => fake()->unique()->regexify('[a-z]{64}'), 'push_token' => fake()->unique()->regexify('[a-z]{40}'), 'push_platform' => 'ios']);

        return $user;
    }

    private function incident(array $attributes = []): Incident
    {
        return Incident::create([
            'category' => 'brand', 'subtype' => 'Brand', 'status' => 'officieel', 'visibility' => 'public', 'source' => 'p2000_zwaailicht',
            'latitude' => 52.3752, 'longitude' => 4.8952, 'severity' => 4, 'confidence' => 1, 'confirmations_count' => 0, 'incident_type' => 'event',
            'first_observed_at' => now(), 'last_activity_at' => now(), 'expires_at' => now()->addHour(), ...$attributes,
        ]);
    }

    public function test_a_place_in_range_gets_one_danger_alert_per_device_and_it_is_not_repeated(): void
    {
        $user = $this->subscriber();
        $user->places()->create(['name' => 'Thuis', 'latitude' => 52.3702, 'longitude' => 4.8952, 'radius_m' => 500]);
        $incident = $this->incident();

        $engine = app(AlertEngine::class);
        $this->assertSame(1, $engine->queueFor($incident));
        $this->assertSame(0, $engine->queueFor($incident));

        $delivery = AlertDelivery::sole();
        $this->assertSame('danger', $delivery->level);
        $this->assertSame('queued', $delivery->status);
        $this->assertStringContainsString('Thuis', $delivery->payload['title']);
    }

    public function test_more_confirmations_reach_places_further_away(): void
    {
        $user = $this->subscriber();
        // ~1,9 km van het incident, straal 100 m: buiten het bereik van één onbevestigde melding (600 m),
        // binnen dat van zes bevestigingen (~1,9 km).
        $user->places()->create(['name' => 'Werk', 'latitude' => 52.3872, 'longitude' => 4.8952, 'radius_m' => 100]);
        $incident = $this->incident(['source' => 'user', 'confirmations_count' => 1]);

        $engine = app(AlertEngine::class);
        $this->assertSame(0, $engine->queueFor($incident));

        $incident->update(['confirmations_count' => 6]);
        $this->assertSame(1, $engine->queueFor($incident->fresh()));
    }

    public function test_filters_unsubscribed_users_and_far_places_are_respected(): void
    {
        $this->subscriber(['alert_filters' => ['categories' => ['verkeer'], 'min_level' => 'warn']])
            ->places()->create(['name' => 'Thuis', 'latitude' => 52.3702, 'longitude' => 4.8952, 'radius_m' => 500]);
        $this->subscriber(['subscription_expires_at' => now()->subDay()])
            ->places()->create(['name' => 'Thuis', 'latitude' => 52.3702, 'longitude' => 4.8952, 'radius_m' => 500]);
        $this->subscriber()->places()->create(['name' => 'Ver weg', 'latitude' => 53.2, 'longitude' => 6.5, 'radius_m' => 5000]);

        $this->assertSame(0, app(AlertEngine::class)->queueFor($this->incident()));
        $this->assertDatabaseCount('alert_deliveries', 0);
    }

    public function test_info_is_bundled_into_one_silent_digest(): void
    {
        $user = $this->subscriber(['alert_filters' => ['min_level' => 'info']]);
        $user->places()->create(['name' => 'Thuis', 'latitude' => 52.3702, 'longitude' => 4.8952, 'radius_m' => 2000]);
        $engine = app(AlertEngine::class);

        foreach (['Gaslucht', 'Oliespoor / gevaarlijke vloeistof', 'Wegafsluiting'] as $subtype) {
            $this->assertSame(0, $engine->queueFor($this->incident(['subtype' => $subtype, 'severity' => 2, 'category' => 'stoffen', 'expires_at' => now()->addHours(3)])));
        }
        $this->assertSame(3, AlertDelivery::where('status', 'digest')->count());

        $this->travel(61)->minutes();
        $this->assertSame(1, $engine->flushDigests());
        $digest = AlertDelivery::where('status', 'queued')->sole();
        $this->assertSame('info', $digest->level);
        $this->assertStringContainsString('3', $digest->payload['title']);
    }

    public function test_an_escalation_alerts_again_but_the_same_level_does_not(): void
    {
        $user = $this->subscriber(['alert_filters' => ['min_level' => 'warn']]);
        $user->places()->create(['name' => 'Thuis', 'latitude' => 52.3702, 'longitude' => 4.8952, 'radius_m' => 500]);
        $incident = $this->incident(['severity' => 3]);
        $engine = app(AlertEngine::class);

        $this->assertSame(1, $engine->queueFor($incident));
        $this->assertSame(0, $engine->queueFor($incident));
        $incident->update(['severity' => 5]);
        $this->assertSame(1, $engine->queueFor($incident->fresh()));
    }

    public function test_quiet_hours_hold_warnings_but_not_danger(): void
    {
        $this->travelTo(now('Europe/Amsterdam')->setTime(2, 0)->utc());
        $user = $this->subscriber(['quiet_hours' => ['enabled' => true, 'from' => '23:00', 'to' => '07:00']]);
        $user->places()->create(['name' => 'Thuis', 'latitude' => 52.3702, 'longitude' => 4.8952, 'radius_m' => 500]);
        $engine = app(AlertEngine::class);

        $this->assertSame(0, $engine->queueFor($this->incident(['severity' => 3])));
        $this->assertSame(1, $engine->queueFor($this->incident(['severity' => 5])));
    }

    public function test_alerts_are_written_in_the_users_language_and_sent_to_their_agent(): void
    {
        Bus::fake();
        $user = $this->subscriber(['locale' => 'en']);
        $user->forceFill(['webhook_url' => 'https://agent.example/hook', 'webhook_secret' => 'whsec_test'])->save();
        $user->places()->create(['name' => 'Home', 'latitude' => 52.3702, 'longitude' => 4.8952, 'radius_m' => 500]);

        app(AlertEngine::class)->queueFor($this->incident(['subtype' => 'Bommelding']));

        $this->assertSame('Bomb threat · Home', AlertDelivery::sole()->payload['title']);
        Bus::assertDispatched(\App\Jobs\SendAgentWebhook::class);
    }
}
