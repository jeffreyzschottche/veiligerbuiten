<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\IncidentObservation;
use App\Models\SourceRecord;
use Carbon\Carbon;

class ExternalIncidentIngestor
{
    public function __construct(private readonly IncidentEngine $engine) {}

    public function ndw(array $item): Incident
    {
        $type = strtolower($item['type'] ?? '');
        $comment = trim($item['comment'] ?? '');
        [$category, $subtype, $severity] = match (true) {
            str_contains($type, 'accident') || str_contains($type, 'collision') => ['verkeer', 'Verkeersongeval', 4],
            str_contains($type, 'roadworks') || str_contains($type, 'management') || str_contains($type, 'closure') => ['blokkade', 'Weg geblokkeerd', 3],
            str_contains($type, 'obstruction') => ['verkeer', $this->ndwObstructionSubtype($item, $comment), 3],
            str_contains($type, 'hazard') => ['verkeer', 'Gevaarlijke verkeerssituatie', 3],
            default => ['verkeer', 'Verkeerssituatie', 2],
        };

        $incident = $this->upsertOfficial('ndw', $item, [
            'category' => $category, 'subtype' => $subtype,
            'description' => $comment ?: 'Actuele verkeersmelding van NDW.',
            'severity' => $severity, 'incident_type' => 'traffic', 'fallback_hours' => 2,
        ]);

        SourceRecord::updateOrCreate(
            ['source' => 'ndw', 'external_id' => $item['id']],
            ['payload_hash' => hash('sha256', json_encode($item)), 'payload' => $item, 'received_at' => now(), 'processed_at' => now(), 'status' => 'processed'],
        );
        $this->recordObservation($incident, 'ndw', $item);

        return $incident;
    }

    public function knmi(array $item): Incident
    {
        $severity = match (strtolower($item['level'] ?? '')) { 'red' => 5, 'orange' => 4, default => 3 };
        $incident = $this->upsertOfficial('knmi', $item, [
            'category' => 'weer', 'subtype' => $item['title'] ?? 'Officiële weerwaarschuwing',
            'description' => $item['description'] ?? 'Officiële waarschuwing van het KNMI.',
            'severity' => $severity, 'incident_type' => 'weather', 'fallback_hours' => 6,
        ]);
        SourceRecord::updateOrCreate(['source' => 'knmi', 'external_id' => $item['id']], ['payload_hash' => hash('sha256', json_encode($item)), 'payload' => $item, 'received_at' => now(), 'processed_at' => now(), 'status' => 'processed']);
        $this->recordObservation($incident, 'knmi', $item);
        return $incident;
    }

    public function luchtmeetnet(array $item): Incident
    {
        return $this->officialIncident('luchtmeetnet', $item, [
            'category' => 'stoffen', 'subtype' => 'Verhoogde luchtwaarde gemeten', 'severity' => 2,
            'incident_type' => 'air_quality', 'description' => 'Verhoogde luchtwaarde gemeten door Luchtmeetnet.',
        ], 2);
    }

    public function rws(array $item): Incident
    {
        return $this->officialIncident('rijkswaterstaat', $item, [
            'category' => 'weer', 'subtype' => 'Hoge waterstand gemeten', 'severity' => 3,
            'incident_type' => 'water', 'description' => 'Officiële waterstandsmelding van Rijkswaterstaat.',
        ], 2);
    }

    public function politie(array $item): Incident
    {
        return $this->officialIncident('politie', $item, [
            'category' => 'onveilig', 'subtype' => 'Politiebericht', 'severity' => 2,
            'incident_type' => 'police_notice', 'description' => 'Actueel lokaal bericht van de politie.',
        ], 24);
    }

    /** NL-Alert: altijd gevaar; het gebied van de waarschuwing wordt de geometrie. */
    public function nlAlert(array $item): Incident
    {
        $incident = $this->upsertOfficial('nl_alert', $item, [
            'category' => $item['category'], 'subtype' => 'NL-Alert', 'description' => $item['description'],
            'severity' => 5, 'incident_type' => 'alert', 'fallback_hours' => 2,
        ]);
        if ($item['geometry'] ?? null) {
            $incident->update(['geometry' => $item['geometry']]);
        }
        $this->record('nl_alert', $item);

        return $incident->fresh();
    }

    /** Aardbeving (KNMI). Ernst volgt de magnitude; voelbaar vanaf ongeveer 2,0. */
    public function quake(array $item): Incident
    {
        $magnitude = (float) $item['magnitude'];
        $incident = $this->upsertOfficial('knmi_quakes', $item, [
            'category' => 'anders', 'subtype' => 'Aardbeving', 'description' => $item['description'],
            'severity' => match (true) { $magnitude >= 3.0 => 4, $magnitude >= 2.0 => 3, default => 2 },
            'incident_type' => 'earthquake', 'fallback_hours' => 6,
        ]);
        $this->record('knmi_quakes', $item);

        return $incident;
    }

    private function record(string $source, array $item): void
    {
        SourceRecord::updateOrCreate(['source' => $source, 'external_id' => $item['id']], ['payload_hash' => hash('sha256', json_encode($item)), 'payload' => $item, 'received_at' => now(), 'processed_at' => now(), 'status' => 'processed']);
    }

    public function p2000(array $item): Incident
    {
        $source = 'p2000_' . ($item['source_feed'] ?? 'unknown');
        $incidentType = match ($item['category']) {
            'brand' => 'fire',
            'geweld' => 'violence',
            default => 'emergency',
        };

        $incident = $this->upsertOfficial($source, $item, [
            'category' => $item['category'],
            'subtype' => $item['subtype'],
            'description' => $item['description'] ?? $item['title'],
            'severity' => $item['severity'] ?? 3,
            'incident_type' => $incidentType,
            'fallback_hours' => 2,
        ]);

        SourceRecord::updateOrCreate(
            ['source' => $source, 'external_id' => $item['id']],
            [
                'payload_hash' => hash('sha256', json_encode($item)),
                'payload' => $item,
                'received_at' => now(),
                'processed_at' => now(),
                'status' => 'processed',
            ],
        );

        $this->recordObservation($incident, $source, $item);

        return $incident;
    }

    /** @param array{category:string, subtype:string, severity:int, incident_type:string, description:string} $defaults */
    private function officialIncident(string $source, array $item, array $defaults, int $fallbackHours): Incident
    {
        return $this->upsertOfficial($source, $item, [
            'category' => $item['category'] ?? $defaults['category'], 'subtype' => $item['subtype'] ?? $defaults['subtype'],
            'description' => $item['description'] ?? $defaults['description'], 'severity' => $item['severity'] ?? $defaults['severity'],
            'incident_type' => $defaults['incident_type'], 'fallback_hours' => $fallbackHours,
        ]);
    }

    private function ndwObstructionSubtype(array $item, string $comment): string
    {
        $detail = strtolower(($item['subtype'] ?? '').' '.$comment);
        return match (true) {
            str_contains($detail, 'object') => 'Object op de rijbaan',
            str_contains($detail, 'vehicle') || str_contains($detail, 'voertuig') => 'Voertuig op gevaarlijke plek',
            str_contains($detail, 'pijlwagen') => 'Voertuig op gevaarlijke plek',
            default => 'Gevaarlijke verkeerssituatie',
        };
    }

    /** Keep imported alerts current: an external end time can never extend past 24 hours. */
    private function expiry(?string $endsAt, int $fallbackHours): Carbon
    {
        $maximum = now()->addHours(24);

        if (! $endsAt) {
            return now()->addHours($fallbackHours);
        }

        $candidate = Carbon::parse($endsAt);

        return $candidate->greaterThan($maximum) ? $maximum : $candidate;
    }

    private function recordObservation(Incident $incident, string $source, array $item): void
    {
        IncidentObservation::updateOrCreate(
            ['source' => $source, 'source_reference' => (string) $item['id']],
            [
                'incident_id' => $incident->id,
                'kind' => 'external_update',
                'status' => 'accepted',
                'category' => $incident->category,
                'subtype' => $incident->subtype,
                'description' => $item['description'] ?? null,
                'latitude' => $item['latitude'],
                'longitude' => $item['longitude'],
                'observed_at' => Carbon::parse($item['updated_at'] ?? now()),
                'metadata' => $item,
            ],
        );
    }

    /** Store each source record separately, but merge compatible active situations into one incident. */
    private function upsertOfficial(string $source, array $item, array $attributes): Incident
    {
        $existingObservation = IncidentObservation::query()
            ->where('source', $source)->where('source_reference', (string) $item['id'])->first();
        $incident = $existingObservation ? Incident::find($existingObservation->incident_id) : null;
        $category = $attributes['category'];
        if (! $incident) $incident = $this->engine->findDuplicate($category, (float) $item['latitude'], (float) $item['longitude']);

        $payload = [
            'category' => $category, 'subtype' => $attributes['subtype'], 'description' => $attributes['description'],
            'status' => 'officieel', 'visibility' => 'public', 'latitude' => $item['latitude'], 'longitude' => $item['longitude'],
            'severity' => $attributes['severity'], 'confidence' => 1, 'incident_type' => $attributes['incident_type'],
            'last_confirmed_at' => Carbon::parse($item['updated_at'] ?? now()), 'last_activity_at' => now(),
            'expires_at' => $this->expiry($item['ends_at'] ?? null, $attributes['fallback_hours']),
        ];

        if (! $incident) {
            $incident = Incident::create($payload + [
                'source' => $source, 'source_reference' => (string) $item['id'], 'confirmations_count' => 0,
                'first_observed_at' => Carbon::parse($item['updated_at'] ?? now()),
            ]);
        } else {
            // Samenvoegen met een ánder item op dezelfde plek mag de ernst nooit verlagen
            // (een ambulancerit naast een bommelding blijft een bommelding).
            if (! $existingObservation) {
                $payload['severity'] = max($payload['severity'], (int) $incident->severity);
                if ($payload['severity'] > $attributes['severity']) {
                    unset($payload['category'], $payload['subtype'], $payload['description']);
                }
            }
            // The first official source becomes canonical; every later source remains an observation.
            if ($incident->source === 'user') $payload += ['source' => $source, 'source_reference' => (string) $item['id']];
            $incident->update($payload);
        }
        $this->recordObservation($incident, $source, $item);
        return $incident->fresh();
    }
}
