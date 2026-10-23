<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\IncidentReport;
use App\Models\Installation;
use App\Models\IncidentObservation;
use App\Models\ModerationEvent;
use App\Services\IncidentEngine;
use App\Services\IncidentSafetyPolicy;
use App\Services\InstallationTrust;
use App\Services\ReportQuota;
use App\Support\GeoUtils;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    public function __construct(private readonly IncidentEngine $engine, private readonly IncidentSafetyPolicy $policy, private readonly InstallationTrust $trust, private readonly ReportQuota $quota) {}
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category' => 'required|in:brand,geweld,onveilig,verkeer,blokkade,weer,stoffen,drukte,dier,anders',
            'subtype' => 'required|string|max:80', 'description' => 'nullable|string|max:180',
            'latitude' => 'required|numeric|between:50.5,53.7', 'longitude' => 'required|numeric|between:3.1,7.3',
            'user_latitude' => 'nullable|numeric|between:50.5,53.7', 'user_longitude' => 'nullable|numeric|between:3.1,7.3',
            'location_accuracy_m' => 'nullable|numeric|between:0,5000',
        ]);
        $description = trim($data['description'] ?? '');
        if (($data['category'] === 'anders' || str_starts_with($data['subtype'], 'Anders:')) && $description === '') {
            throw ValidationException::withMessages(['description' => 'Geef bij Anders een korte toelichting.']);
        }
        if ($reason = $this->invalidDescriptionReason($description)) {
            throw ValidationException::withMessages(['description' => $reason]);
        }
        $installation = Installation::where('token', $request->header('X-Installation-Token'))->firstOrFail();
        abort_unless($this->trust->mayContribute($installation), 403, 'Deze installatie kan tijdelijk geen meldingen plaatsen.');
        abort_unless(isset($data['user_latitude'], $data['user_longitude']), 422, 'We hebben je actuele locatie nodig om een melding te plaatsen.');
        abort_if(($data['location_accuracy_m'] ?? 0) > 250, 422, 'Je locatie is nog niet nauwkeurig genoeg. Probeer het buiten opnieuw.');
        abort_if(GeoUtils::distanceMeters($data['latitude'], $data['longitude'], $data['user_latitude'], $data['user_longitude']) > 1000, 422, 'Een melding moet binnen 1 km van je actuele locatie liggen.');
        $user = $request->user();
        // Eén transactie onder een lock per gebruiker: het dagquotum kan niet worden omzeild.
        [$incident, $nearby] = $this->quota->consume($user, function () use ($data, $description, $installation, $user) {
            $nearby = $this->engine->findDuplicate($data['category'], $data['latitude'], $data['longitude']);
            $visibility = $this->policy->initialVisibility($data['category']);
            $incident = $nearby ?: Incident::create([...$data, 'status' => 'gemeld', 'visibility' => $visibility, 'source' => 'user', 'incident_type' => $this->incidentType($data['category']), 'severity' => $this->severity($data['category']), 'confidence' => .20, 'confirmations_count' => 0, 'last_confirmed_at' => now(), 'first_observed_at' => now(), 'last_activity_at' => now(), 'location_precision_m' => isset($data['location_accuracy_m']) ? min(5000, (int) $data['location_accuracy_m']) : null, 'expires_at' => now()->addHours(min(24, $this->policy->expiryHours($data['category'], $data['subtype'])))]);
            $report = IncidentReport::create([
                ...$data,
                'incident_id' => $incident->id,
                'installation_id' => $installation->id,
                'user_id' => $user->id,
                'status' => $visibility === 'review' ? 'review' : 'accepted',
                'source' => 'user',
                'reported_at' => now(),
            ]);
            IncidentObservation::create(['incident_id' => $incident->id, 'installation_id' => $installation->id, 'source' => 'user', 'source_reference' => 'report:'.$report->id, 'kind' => 'report', 'status' => $report->status, 'category' => $data['category'], 'subtype' => $data['subtype'], 'description' => $description ?: null, 'latitude' => $data['latitude'], 'longitude' => $data['longitude'], 'observed_at' => now(), 'metadata' => ['location_accuracy_m' => $data['location_accuracy_m'] ?? null]]);
            if ($visibility === 'review') {
                ModerationEvent::create(['incident_id' => $incident->id, 'installation_id' => $installation->id, 'action' => 'queued_for_review', 'reason' => 'sensitive_user_category']);
            }
            $incident->increment('confirmations_count');
            $incident->update(['last_confirmed_at' => now()]);
            $incident = $this->engine->updateConfidence($incident);

            return [$incident, $nearby];
        });
        return response()->json(['data' => $incident->fresh(), 'duplicate' => (bool) $nearby, 'visibility' => $incident->visibility, 'quota' => $this->quota->summary($user)], $nearby ? 200 : 201);
    }

    public function quota(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->quota->summary($request->user())]);
    }

    private function invalidDescriptionReason(string $text): ?string
    {
        if ($text === '') return null;
        if (preg_match('/\d/u', $text)) return 'Je mag geen cijfers gebruiken.';
        if (! preg_match('/^[\p{L}\s,.!]+$/u', $text)) return 'Gebruik alleen letters, spaties, komma’s, punten en uitroeptekens.';
        if (preg_match('/\b(https?|www|\.nl|\.com|gmail|hotmail|outlook)\b/ui', $text) || preg_match('/[\p{L}._%+-]+@[\p{L}.-]+\.[\p{L}]{2,}/u', $text)) return 'Links, e-mailadressen en contactgegevens zijn niet toegestaan.';
        if (preg_match('/\b(korting|aanbieding|koop|volg mij|instagram|telegram|whatsapp|dm)\b/ui', $text)) return 'Promotie en reclame zijn niet toegestaan.';
        if (preg_match('/\b(kanker|tering|hoer|kut|neuk|fuck)\b/ui', $text)) return 'Scheldwoorden zijn niet toegestaan.';
        if (preg_match('/\b(telefoonnummer|bsn|adres van|dood aan)\b/ui', $text)) return 'Persoonsgegevens en bedreigingen zijn niet toegestaan.';
        if (preg_match('/(.)\1{4,}/u', $text) || preg_match('/\b([\p{L}]+)(?:\s+\1){2,}\b/ui', $text)) return 'De omschrijving lijkt spam te bevatten.';

        return null;
    }

    private function severity(string $category): int { return match ($category) { 'brand', 'stoffen', 'geweld' => 4, 'weer', 'blokkade', 'verkeer' => 3, default => 2 }; }
    private function incidentType(string $category): string { return match ($category) { 'weer', 'drukte' => 'area_condition', 'blokkade', 'verkeer' => 'hazard', default => 'event' }; }
}
