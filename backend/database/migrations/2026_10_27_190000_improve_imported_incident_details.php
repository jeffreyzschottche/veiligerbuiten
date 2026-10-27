<?php

use App\Models\Incident;
use App\Models\IncidentObservation;
use App\Models\SourceRecord;
use App\Support\NdwIncidentDetails;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Alleen rustige medische inzetten omzetten; dreigingen en ongevallen niet afzwakken.
        Incident::query()->where('category', 'onveilig')->where('severity', '<=', 2)
            ->where('source', 'like', 'p2000%')
            ->whereIn('subtype', ['Medische noodsituatie', 'Traumahelikopter ingezet'])
            ->chunkById(200, function ($incidents): void {
                foreach ($incidents as $incident) {
                    $incident->category = 'medisch';
                    $incident->incident_type = 'medical';
                    if ($incident->subtype === 'Medische noodsituatie') $incident->subtype = 'Ambulance-inzet';
                    $incident->save();
                }
            });

        IncidentObservation::query()->where('category', 'onveilig')->where('source', 'like', 'p2000%')
            ->whereIn('subtype', ['Medische noodsituatie', 'Traumahelikopter ingezet'])
            ->chunkById(200, function ($observations): void {
                foreach ($observations as $observation) {
                    if ((int) ($observation->metadata['severity'] ?? 3) > 2) continue;
                    $observation->category = 'medisch';
                    if ($observation->subtype === 'Medische noodsituatie') $observation->subtype = 'Ambulance-inzet';
                    $observation->save();
                }
            });

        // Gebruik bewaarde brongegevens: geen netwerkverzoek en geen heropening van oude meldingen.
        Incident::query()->where('source', 'ndw')->whereNull('resolved_at')->where('expires_at', '>', now())
            ->whereIn('description', ['Actuele verkeersmelding van NDW.', 'Pijlwagen', 'Botsabsorber', 'Chevron'])
            ->chunkById(200, function ($incidents): void {
                foreach ($incidents as $incident) {
                    $payload = SourceRecord::query()->where('source', 'ndw')->where('external_id', $incident->source_reference)->first()?->payload;
                    if (! $payload) continue;
                    $details = NdwIncidentDetails::describe($payload);
                    $incident->fill(['subtype' => $details['subtype'], 'description' => $details['description']])->save();
                }
            });
    }

    public function down(): void
    {
        // De verbeterde teksten en categorieën zijn gegevens; die draaien we niet terug.
    }
};
