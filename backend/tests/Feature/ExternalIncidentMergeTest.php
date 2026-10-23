<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\IncidentObservation;
use App\Services\ExternalIncidentIngestor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExternalIncidentMergeTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_official_ndw_signal_upgrades_a_nearby_user_incident_instead_of_creating_a_duplicate(): void
    {
        $userIncident = Incident::create([
            'category' => 'verkeer', 'subtype' => 'Object op de rijbaan', 'status' => 'gemeld', 'visibility' => 'public',
            'source' => 'user', 'latitude' => 52.3702, 'longitude' => 4.8952, 'severity' => 3,
            'confidence' => .20, 'incident_type' => 'event', 'confirmations_count' => 1,
            'first_observed_at' => now(), 'last_activity_at' => now(), 'expires_at' => now()->addHours(2),
        ]);

        $incident = app(ExternalIncidentIngestor::class)->ndw([
            'id' => 'ndw-test-1', 'type' => 'obstruction', 'latitude' => 52.3705, 'longitude' => 4.8955,
            'comment' => 'Object op de rijbaan', 'updated_at' => now()->toIso8601String(),
        ]);

        $this->assertSame($userIncident->id, $incident->id);
        $this->assertDatabaseCount('incidents', 1);
        $this->assertDatabaseHas('incidents', ['id' => $userIncident->id, 'source' => 'ndw', 'status' => 'officieel', 'visibility' => 'public']);
        $this->assertDatabaseHas('incident_observations', ['incident_id' => $userIncident->id, 'source' => 'ndw', 'source_reference' => 'ndw-test-1']);
    }

    public function test_the_same_external_record_updates_its_existing_observation(): void
    {
        $ingestor = app(ExternalIncidentIngestor::class);
        $item = ['id' => 'ndw-test-2', 'type' => 'accident', 'latitude' => 52.3702, 'longitude' => 4.8952, 'updated_at' => now()->toIso8601String()];
        $first = $ingestor->ndw($item);
        $second = $ingestor->ndw([...$item, 'comment' => 'Bijgewerkte verkeerssituatie']);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('incidents', 1);
        $this->assertSame(1, IncidentObservation::where('source', 'ndw')->where('source_reference', 'ndw-test-2')->count());
    }
}
