<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\SourceRecord;
use App\Services\ExternalIncidentIngestor;
use App\Support\NdwIncidentDetails;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NdwDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_import_uses_the_incident_type_instead_of_the_mobility_type(): void
    {
        $start = now()->toIso8601String();
        $xml = <<<XML
        <d2:payload xmlns:d2="http://datex2.eu/schema/3/d2Payload" xmlns:sit="http://datex2.eu/schema/3/situation" xmlns:loc="http://datex2.eu/schema/3/locationReferencing" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
          <sit:situation id="s1"><sit:situationRecord id="vehicle1" xsi:type="sit:VehicleObstruction">
            <sit:validity><sit:overallStartTime>{$start}</sit:overallStartTime></sit:validity>
            <sit:locationReference><loc:pointCoordinates><loc:latitude>52.37</loc:latitude><loc:longitude>4.89</loc:longitude></loc:pointCoordinates><loc:roadNumber>A12</loc:roadNumber><loc:carriageway><loc:carriageway>exitSlipRoad</loc:carriageway></loc:carriageway></sit:locationReference>
            <sit:mobilityOfObstruction><sit:mobilityType>stationary</sit:mobilityType></sit:mobilityOfObstruction>
            <sit:vehicleObstructionType>brokenDownVehicle</sit:vehicleObstructionType>
          </sit:situationRecord></sit:situation>
        </d2:payload>
        XML;
        Http::fake(['opendata.ndw.nu/*' => Http::response(gzencode($xml))]);

        $this->artisan('veiligbuiten:ingest-ndw')->assertSuccessful();

        $incident = Incident::sole();
        $this->assertSame('Voertuig met pech', $incident->subtype);
        $this->assertStringContainsString('voertuig met pech', $incident->description);
        $this->assertStringContainsString('A12', $incident->description);
        $this->assertStringContainsString('afrit', $incident->description);
        $this->assertStringNotContainsString('NDW', $incident->description);
        $this->assertSame('brokenDownVehicle', SourceRecord::sole()->payload['subtype']);
    }

    public function test_empty_comments_still_produce_factual_specific_descriptions(): void
    {
        foreach ([
            ['sit:VehicleObstruction', 'other', 'Voertuig vormt een obstakel', 'Een voertuig vormt een obstakel voor het verkeer.'],
            ['sit:Accident', 'other', 'Verkeersongeval', 'Er is een verkeersongeval gemeld.'],
            ['sit:GeneralObstruction', 'objectOnTheRoad', 'Object op de rijbaan', 'Er is een voorwerp op de rijbaan gemeld.'],
        ] as [$type, $detail, $subtype, $description]) {
            $result = NdwIncidentDetails::describe(['type' => $type, 'subtype' => $detail]);
            $this->assertSame($subtype, $result['subtype']);
            $this->assertSame($description, $result['description']);
        }
        $unknown = NdwIncidentDetails::describe(['type' => 'UnknownType']);
        $this->assertStringContainsString('Verdere details zijn niet beschikbaar', $unknown['description']);
        $this->assertSame(2, $unknown['severity']);
    }

    public function test_work_vehicles_are_explained_and_meaningful_comments_are_preserved(): void
    {
        $result = NdwIncidentDetails::describe(['type' => 'VehicleObstruction', 'comment' => 'Pijlwagen']);
        $this->assertSame('Werkvoertuig bij de weg', $result['subtype']);
        $this->assertSame('Er is een pijlwagen ingezet bij de weg.', $result['description']);
        $result = NdwIncidentDetails::describe(['type' => 'Accident', 'comment' => 'Twee voertuigen betrokken op de A4.']);
        $this->assertSame('Twee voertuigen betrokken op de A4.', $result['description']);
    }

    public function test_an_existing_ndw_record_gets_a_better_text_without_a_duplicate(): void
    {
        $ingestor = app(ExternalIncidentIngestor::class);
        $item = ['id' => 'same', 'type' => 'VehicleObstruction', 'subtype' => 'stationary', 'latitude' => 52.37, 'longitude' => 4.89];
        $first = $ingestor->ndw($item);
        $second = $ingestor->ndw([...$item, 'subtype' => 'brokenDownVehicle']);
        $this->assertSame($first->id, $second->id);
        $this->assertSame('Voertuig met pech', $second->subtype);
        $this->assertDatabaseCount('incidents', 1);
    }
}
