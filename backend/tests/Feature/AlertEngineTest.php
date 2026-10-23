<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\Installation;
use App\Services\AlertEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_one_relevant_direct_alert_and_deduplicates_the_same_update(): void
    {
        Installation::create([
            'token' => str_repeat('p', 64), 'push_token' => str_repeat('device', 8), 'push_platform' => 'android',
            'alert_preferences' => ['direct' => true, 'warning' => false, 'info' => false],
            'last_latitude' => 52.3702, 'last_longitude' => 4.8952, 'last_location_at' => now(),
        ]);
        $incident = Incident::create([
            'category' => 'brand', 'subtype' => 'Brand', 'status' => 'officieel', 'visibility' => 'public', 'source' => 'ndw',
            'latitude' => 52.3752, 'longitude' => 4.8952, 'severity' => 4, 'confidence' => 1, 'incident_type' => 'event',
            'first_observed_at' => now(), 'last_activity_at' => now(), 'expires_at' => now()->addHour(),
        ]);

        $engine = app(AlertEngine::class);
        $this->assertSame(1, $engine->queueFor($incident));
        $this->assertSame(0, $engine->queueFor($incident));
        $this->assertDatabaseHas('alert_deliveries', ['incident_id' => $incident->id, 'status' => 'queued', 'level' => 'direct']);
    }

    public function test_it_never_queues_a_low_confidence_or_far_away_alert(): void
    {
        Installation::create([
            'token' => str_repeat('q', 64), 'push_token' => str_repeat('device', 8), 'push_platform' => 'android',
            'alert_preferences' => ['direct' => true], 'last_latitude' => 53.3702, 'last_longitude' => 4.8952, 'last_location_at' => now(),
        ]);
        $incident = Incident::create([
            'category' => 'brand', 'subtype' => 'Brand', 'status' => 'gemeld', 'visibility' => 'public', 'source' => 'user',
            'latitude' => 52.3752, 'longitude' => 4.8952, 'severity' => 4, 'confidence' => .20, 'incident_type' => 'event',
            'first_observed_at' => now(), 'last_activity_at' => now(), 'expires_at' => now()->addHour(),
        ]);

        $this->assertSame(0, app(AlertEngine::class)->queueFor($incident));
        $this->assertDatabaseCount('alert_deliveries', 0);
    }
}
