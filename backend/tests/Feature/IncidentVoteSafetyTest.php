<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\AlertDelivery;
use App\Models\Installation;
use App\Models\SourceIngestionRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentVoteSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_vote_requires_an_installation_token_and_can_only_be_cast_once(): void
    {
        $installation = Installation::create(['token' => str_repeat('a', 64), 'trust_score' => 50]);
        $incident = $this->incident();

        $this->postJson("/api/v1/incidents/{$incident->id}/confirm")
            ->assertNotFound();

        $response = $this->postJson("/api/v1/incidents/{$incident->id}/confirm", [
            'latitude' => 52.3702,
            'longitude' => 4.8952,
        ], ['X-Installation-Token' => $installation->token]);

        $response->assertOk()->assertJsonPath('vote', 'present');
        $this->assertDatabaseHas('incident_votes', ['incident_id' => $incident->id, 'installation_id' => $installation->id, 'vote' => 'present']);
        $this->assertDatabaseHas('incident_observations', ['incident_id' => $incident->id, 'installation_id' => $installation->id, 'kind' => 'present']);

        $this->postJson("/api/v1/incidents/{$incident->id}/resolve", [], ['X-Installation-Token' => $installation->token])
            ->assertConflict();
    }

    public function test_review_incidents_are_not_exposed_by_the_public_map_endpoint(): void
    {
        $this->incident(['visibility' => 'review']);

        $this->getJson('/api/v1/incidents?latitude=52.3702&longitude=4.8952')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_an_installation_below_the_trust_floor_cannot_vote(): void
    {
        $installation = Installation::create(['token' => str_repeat('b', 64), 'trust_score' => 9]);
        $incident = $this->incident();

        $this->postJson("/api/v1/incidents/{$incident->id}/confirm", [], ['X-Installation-Token' => $installation->token])
            ->assertForbidden();
    }

    public function test_health_endpoint_checks_database_and_cache(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database', 'ok')
            ->assertJsonPath('checks.cache', 'ok');
    }

    public function test_the_map_endpoint_enforces_a_real_circular_radius(): void
    {
        $this->incident(['latitude' => 52.5502, 'longitude' => 5.1452]);

        $this->getJson('/api/v1/incidents?latitude=52.3702&longitude=4.8952&radius_km=25')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_source_status_reports_the_latest_success_without_exposing_internal_errors(): void
    {
        SourceIngestionRun::create(['source' => 'ndw', 'status' => 'success', 'started_at' => now()->subMinute(), 'finished_at' => now()->subMinute()]);
        SourceIngestionRun::create(['source' => 'ndw', 'status' => 'failed', 'error_message' => 'private upstream details', 'started_at' => now(), 'finished_at' => now()]);

        $this->getJson('/api/v1/sources/status')->assertOk()
            ->assertJsonPath('data.0.source', 'ndw')
            ->assertJsonPath('data.0.state', 'degraded')
            ->assertJsonMissing(['error_message' => 'private upstream details']);
    }

    public function test_a_native_push_token_is_bound_to_the_anonymous_installation(): void
    {
        $installation = Installation::create(['token' => str_repeat('c', 64), 'trust_score' => 50]);

        $this->putJson('/api/v1/installations/push-token', [
            'push_token' => str_repeat('token', 12), 'platform' => 'android',
        ], ['X-Installation-Token' => $installation->token])
            ->assertOk()
            ->assertJsonPath('data.registered', true);

        $this->assertDatabaseHas('installations', ['id' => $installation->id, 'push_token' => str_repeat('token', 12), 'push_platform' => 'android']);
    }

    public function test_only_the_receiving_installation_can_mark_an_alert_as_opened(): void
    {
        $owner = Installation::create(['token' => str_repeat('d', 64), 'trust_score' => 50]);
        $other = Installation::create(['token' => str_repeat('e', 64), 'trust_score' => 50]);
        $incident = $this->incident();
        $delivery = AlertDelivery::create([
            'incident_id' => $incident->id,
            'installation_id' => $owner->id,
            'event_key' => 'officieel:123',
            'level' => 'direct',
            'status' => 'sent',
            'payload' => [],
        ]);

        $this->postJson("/api/v1/alert-deliveries/{$delivery->id}/opened", [], ['X-Installation-Token' => $other->token])
            ->assertNotFound();

        $this->postJson("/api/v1/alert-deliveries/{$delivery->id}/opened", [], ['X-Installation-Token' => $owner->token])
            ->assertOk()
            ->assertJsonPath('data.opened', true);

        $this->assertDatabaseMissing('alert_deliveries', ['id' => $delivery->id, 'opened_at' => null]);
    }

    public function test_the_location_retention_command_clears_only_locations_older_than_one_day(): void
    {
        $old = Installation::create([
            'token' => str_repeat('f', 64), 'last_latitude' => 52.3702, 'last_longitude' => 4.8952,
            'last_location_at' => now()->subDay()->subMinute(),
        ]);
        $fresh = Installation::create([
            'token' => str_repeat('g', 64), 'last_latitude' => 52.3702, 'last_longitude' => 4.8952,
            'last_location_at' => now(),
        ]);

        $this->artisan('veiligbuiten:prune-installation-locations')->assertSuccessful();

        $this->assertDatabaseHas('installations', ['id' => $old->id, 'last_latitude' => null, 'last_longitude' => null, 'last_location_at' => null]);
        $this->assertDatabaseHas('installations', ['id' => $fresh->id, 'last_latitude' => 52.3702, 'last_longitude' => 4.8952]);
    }

    private function incident(array $overrides = []): Incident
    {
        return Incident::create(array_merge([
            'category' => 'verkeer', 'subtype' => 'Verkeersongeval', 'status' => 'gemeld',
            'visibility' => 'public', 'source' => 'user', 'latitude' => 52.3702, 'longitude' => 4.8952,
            'severity' => 3, 'confidence' => .20, 'incident_type' => 'event', 'confirmations_count' => 1,
            'resolved_count' => 0, 'last_confirmed_at' => now(), 'first_observed_at' => now(),
            'last_activity_at' => now(), 'expires_at' => now()->addHour(),
        ], $overrides));
    }
}
