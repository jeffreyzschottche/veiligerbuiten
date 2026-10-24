<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\Installation;
use App\Models\IncidentObservation;
use App\Services\AreaSync;
use App\Services\IncidentEngine;
use App\Services\InstallationTrust;
use App\Support\IncidentPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class IncidentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        [$latitude, $longitude, $radius] = $this->area($request);

        $incidents = $this->activeWithin($latitude, $longitude, $radius)
            ->take(150)
            ->map(fn (Incident $incident) => IncidentPresenter::present($incident, $latitude, $longitude))
            ->values();

        return response()->json(['data' => $incidents]);
    }

    /**
     * Alles binnen 30 km voor de lokale cache van de app. De app synchroniseert
     * op de gekozen frequentie (of na een grote verplaatsing) en controleert
     * daartussen zelf. Zie AreaSync: gedeelde cache per buurt, 304 als er niets
     * veranderde en alleen wijzigingen als de app een cursor meestuurt.
     */
    public function sync(Request $request, AreaSync $sync): Response|JsonResponse
    {
        $data = $request->validate([
            'latitude' => 'required|numeric|between:50.5,53.7',
            'longitude' => 'required|numeric|between:3.1,7.3',
            'since' => 'nullable|integer|min:0',
        ]);
        [$latitude, $longitude] = $sync->snap((float) $data['latitude'], (float) $data['longitude']);
        $radius = config('veiligbuiten.sync.radius_km');
        ['etag' => $etag, 'cursor' => $cursor] = $sync->stamp($latitude, $longitude, $radius, app()->getLocale());
        $sync->remember($latitude, $longitude, $radius, app()->getLocale(), $cursor);
        $headers = ['ETag' => $etag, 'Cache-Control' => 'private, no-cache', 'Vary' => 'Accept-Language, Authorization, Accept-Encoding'];

        if (in_array($etag, array_map('trim', explode(',', (string) $request->header('If-None-Match'))), true)) {
            return response('', 304, $headers);
        }

        $delta = isset($data['since']) ? $sync->sharedDelta($latitude, $longitude, $radius, Carbon::createFromTimestamp((int) $data['since']), $etag) : null;
        $payload = $delta ? ['mode' => 'delta', ...$delta] : ['mode' => 'full', ...$sync->full($latitude, $longitude, $radius, $etag), 'removed' => []];

        return response()->json([
            ...$payload,
            'center' => ['latitude' => $latitude, 'longitude' => $longitude],
            'radius_km' => $radius,
            // De staat zelf als cursor: telefoons met dezelfde staat delen hun volgende delta.
            'cursor' => $cursor,
            'generated_at' => now()->toIso8601String(),
            'next_sync_after' => $sync->nextSyncAfter($payload['data'] ?? $payload['changes']),
            'min_sync_after' => config('veiligbuiten.sync.min_interval_seconds'),
        ], 200, $headers);
    }

    public function geojson(Request $request): JsonResponse
    {
        [$latitude, $longitude, $radius] = $this->area($request);
        $features = $this->activeWithin($latitude, $longitude, $radius)->map(fn (Incident $incident) => [
            'type' => 'Feature',
            'id' => $incident->id,
            'geometry' => $incident->geometry ?: ['type' => 'Point', 'coordinates' => [$incident->longitude, $incident->latitude]],
            'properties' => IncidentPresenter::present($incident, $latitude, $longitude),
        ])->values();

        return response()->json(['type' => 'FeatureCollection', 'features' => $features]);
    }

    public function show(Request $request, Incident $incident): JsonResponse
    {
        abort_if($incident->visibility !== 'public' || $incident->expires_at->isPast() || $incident->resolved_at, 404);

        return response()->json(['data' => IncidentPresenter::present($incident, $request->float('latitude', $incident->latitude), $request->float('longitude', $incident->longitude))]);
    }

    public function confirm(Request $request, Incident $incident, IncidentEngine $engine, InstallationTrust $trust): JsonResponse
    {
        return $this->vote($request, $incident, 'present', $engine, $trust);
    }

    public function resolve(Request $request, Incident $incident, IncidentEngine $engine, InstallationTrust $trust): JsonResponse
    {
        return $this->vote($request, $incident, 'resolved', $engine, $trust);
    }

    /** @return array{float, float, int} */
    private function area(Request $request): array
    {
        return [
            $request->float('latitude', 52.3702),
            $request->float('longitude', 4.8952),
            min(config('veiligbuiten.sync.radius_km'), max(1, $request->integer('radius_km', 25))),
        ];
    }

    /** Actieve, publieke incidenten binnen een echte cirkel (gebieden tot hun rand), zwaarste eerst. */
    private function activeWithin(float $latitude, float $longitude, int $radiusKm): Collection
    {
        return app(AreaSync::class)->active($latitude, $longitude, $radiusKm);
    }

    private function vote(Request $request, Incident $incident, string $vote, IncidentEngine $engine, InstallationTrust $trust): JsonResponse
    {
        abort_if($incident->visibility !== 'public' || $incident->expires_at->isPast() || $incident->resolved_at, 410, 'Deze melding is niet meer actief.');
        $data = $request->validate(['latitude' => 'nullable|numeric|between:50.5,53.7', 'longitude' => 'nullable|numeric|between:3.1,7.3']);
        $installation = Installation::where('token', $request->header('X-Installation-Token'))->firstOrFail();
        abort_unless($trust->mayContribute($installation), 403, 'Deze installatie kan tijdelijk niet stemmen.');
        $user = $request->user();
        // Het toestel moet van dit account zijn (of nog van niemand), anders kan één account met andermans of nieuwe installaties stemmen.
        abort_if($installation->user_id !== null && $installation->user_id !== $user->id, 403, 'Deze installatie hoort bij een ander account.');
        // Eén stem per account én per toestel; extra installaties aanmaken levert geen extra stemmen op.
        abort_if($incident->votes()->where(fn ($q) => $q->where('installation_id', $installation->id)->orWhere('user_id', $user->id))->exists(), 409, 'Je hebt al een beoordeling gegeven.');
        // Wie het zelf meldde, kan het niet ook nog 'bevestigen'.
        abort_if($vote === 'present' && $incident->reports()->where('user_id', $user->id)->exists(), 409, 'Je hebt dit zelf gemeld.');
        $incident = $engine->castVote($incident, $installation->id, $vote, $data['latitude'] ?? null, $data['longitude'] ?? null, $user->id);
        if ($incident->source !== 'user' && $vote === 'present') $trust->adjust($installation, 1, 'confirmed_official_incident', $incident->id);
        IncidentObservation::create(['incident_id' => $incident->id, 'installation_id' => $installation->id, 'source' => 'user', 'source_reference' => 'vote:'.$incident->id.':'.$installation->id, 'kind' => $vote, 'status' => 'accepted', 'category' => $incident->category, 'subtype' => $incident->subtype, 'latitude' => $data['latitude'] ?? null, 'longitude' => $data['longitude'] ?? null, 'observed_at' => now()]);

        return response()->json(['data' => IncidentPresenter::present($incident, $data['latitude'] ?? null, $data['longitude'] ?? null), 'vote' => $vote]);
    }
}
