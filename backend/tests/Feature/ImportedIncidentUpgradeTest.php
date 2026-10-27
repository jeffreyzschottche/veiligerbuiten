<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\IncidentObservation;
use App\Models\SourceRecord;
use App\Services\ExternalIncidentIngestor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportedIncidentUpgradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_data_is_improved_without_extending_or_reopening_incidents(): void
    {
        $base = ['category' => 'onveilig', 'subtype' => 'Medische noodsituatie', 'source' => 'p2000_zwaailicht', 'source_reference' => 'ride', 'description' => 'Ambulance in Leiden', 'severity' => 2, 'status' => 'officieel', 'visibility' => 'public', 'latitude' => 52.16, 'longitude' => 4.49, 'expires_at' => now()->addHour()];
        $ride = Incident::create($base);
        $danger = Incident::create([...$base, 'source_reference' => 'danger', 'severity' => 4]);
        $ndw = Incident::create([...$base, 'source' => 'ndw', 'source_reference' => 'ndw1', 'category' => 'verkeer', 'description' => 'Actuele verkeersmelding van NDW.']);
        $expired = Incident::create([...$base, 'source' => 'ndw', 'source_reference' => 'old', 'category' => 'verkeer', 'description' => 'Actuele verkeersmelding van NDW.', 'expires_at' => now()->subHour(), 'resolved_at' => now()->subHour()]);
        SourceRecord::create(['source' => 'ndw', 'external_id' => 'ndw1', 'payload_hash' => 'test', 'payload' => ['type' => 'VehicleObstruction', 'subtype' => 'stationary'], 'received_at' => now()]);
        $expiresAt = $ride->expires_at->toIso8601String();

        $migration = require database_path('migrations/2026_10_27_190000_improve_imported_incident_details.php');
        $migration->up();
        $migration->up(); // veilig opnieuw uitvoerbaar

        $this->assertSame('medisch', $ride->fresh()->category);
        $this->assertSame('Ambulance-inzet', $ride->fresh()->subtype);
        $this->assertSame($expiresAt, $ride->fresh()->expires_at->toIso8601String());
        $this->assertSame('onveilig', $danger->fresh()->category);
        $this->assertSame('Voertuig vormt een obstakel', $ndw->fresh()->subtype);
        $this->assertSame('Actuele verkeersmelding van NDW.', $expired->fresh()->description);
        $this->assertNotNull($expired->fresh()->resolved_at);
    }

    public function test_a_previously_merged_medical_observation_cannot_downgrade_a_threat(): void
    {
        $incident = Incident::create(['category' => 'onveilig', 'subtype' => 'Bommelding', 'source' => 'p2000_zwaailicht', 'source_reference' => 'bomb', 'description' => 'Bommelding', 'severity' => 5, 'status' => 'officieel', 'visibility' => 'public', 'latitude' => 52.37, 'longitude' => 4.89, 'expires_at' => now()->addHour()]);
        IncidentObservation::create(['incident_id' => $incident->id, 'source' => 'p2000_sirenes', 'source_reference' => 'ride', 'kind' => 'external_update', 'status' => 'accepted', 'category' => 'onveilig', 'subtype' => 'Medische noodsituatie', 'observed_at' => now()]);

        $item = ['id' => 'ride', 'source_feed' => 'sirenes', 'category' => 'medisch', 'subtype' => 'Ambulance-inzet', 'description' => 'Ambulance in Amsterdam', 'severity' => 2, 'latitude' => 52.37, 'longitude' => 4.89];
        app(ExternalIncidentIngestor::class)->p2000($item);

        $this->assertSame(['onveilig', 'Bommelding', 5], [$incident->fresh()->category, $incident->fresh()->subtype, $incident->fresh()->severity]);
        // Ook beschermen als de medische rit als eerste binnenkwam en later een dreiging werd toegevoegd.
        $incident->forceFill(['source' => 'p2000_sirenes', 'source_reference' => 'ride'])->save();
        app(ExternalIncidentIngestor::class)->p2000($item);
        $this->assertSame(['onveilig', 'Bommelding', 5], [$incident->fresh()->category, $incident->fresh()->subtype, $incident->fresh()->severity]);
    }
}
