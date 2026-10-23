<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\Installation;
use App\Models\IncidentObservation;
use App\Services\IncidentEngine;
use App\Services\InstallationTrust;
use App\Support\GeoUtils;
use App\Support\IncidentPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
     * Alles binnen 30 km in één keer, voor de lokale cache van de app. De app
     * synchroniseert ieder uur (of bij een grote verplaatsing) en controleert
     * daartussen zelf; zo blijft het aantal verzoeken laag.
     */
    public function sync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'latitude' => 'required|numeric|between:50.5,53.7',
            'longitude' => 'required|numeric|between:3.1,7.3',
        ]);
        $radius = config('veiligbuiten.sync.radius_km');
        $incidents = $this->activeWithin((float) $data['latitude'], (float) $data['longitude'], $radius)
            ->take(500)
            ->map(fn (Incident $incident) => IncidentPresenter::present($incident))
            ->values();

        return response()->json([
            'data' => $incidents,
            'center' => ['latitude' => (float) $data['latitude'], 'longitude' => (float) $data['longitude']],
            'radius_km' => $radius,
            'generated_at' => now()->toIso8601String(),
            'next_sync_after' => config('veiligbuiten.sync.interval_seconds'),
        ])->header('Cache-Control', 'private, max-age=60');
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

    /** Actieve, publieke incidenten binnen een echte cirkel, zwaarste eerst. */
    private function activeWithin(float $latitude, float $longitude, int $radiusKm): Collection
    {
        $latDelta = $radiusKm / 111;
        $lonDelta = $radiusKm / (111 * max(.1, cos(deg2rad($latitude))));

        return Incident::query()
            ->where('visibility', 'public')->where('expires_at', '>', now())
            ->whereNull('resolved_at')
            ->where('resolved_count', '<', 3)
            ->whereBetween('latitude', [$latitude - $latDelta, $latitude + $latDelta])
            ->whereBetween('longitude', [$longitude - $lonDelta, $longitude + $lonDelta])
            ->tap(fn (Builder $query) => $query->orderByDesc('severity')->latest('last_confirmed_at'))
            ->get()
            ->filter(fn (Incident $incident) => GeoUtils::distanceMeters($latitude, $longitude, $incident->latitude, $incident->longitude) <= $radiusKm * 1000);
    }

    private function vote(Request $request, Incident $incident, string $vote, IncidentEngine $engine, InstallationTrust $trust): JsonResponse
    {
        abort_if($incident->visibility !== 'public' || $incident->expires_at->isPast() || $incident->resolved_at, 410, 'Deze melding is niet meer actief.');
        $data = $request->validate(['latitude' => 'nullable|numeric|between:50.5,53.7', 'longitude' => 'nullable|numeric|between:3.1,7.3']);
        $installation = Installation::where('token', $request->header('X-Installation-Token'))->firstOrFail();
        abort_unless($trust->mayContribute($installation), 403, 'Deze installatie kan tijdelijk niet stemmen.');
        abort_if($incident->votes()->where('installation_id', $installation->id)->exists(), 409, 'Je hebt al een beoordeling gegeven.');
        $incident = $engine->castVote($incident, $installation->id, $vote, $data['latitude'] ?? null, $data['longitude'] ?? null);
        if ($incident->source !== 'user' && $vote === 'present') $trust->adjust($installation, 1, 'confirmed_official_incident', $incident->id);
        IncidentObservation::create(['incident_id' => $incident->id, 'installation_id' => $installation->id, 'source' => 'user', 'source_reference' => 'vote:'.$incident->id.':'.$installation->id, 'kind' => $vote, 'status' => 'accepted', 'category' => $incident->category, 'subtype' => $incident->subtype, 'latitude' => $data['latitude'] ?? null, 'longitude' => $data['longitude'] ?? null, 'observed_at' => now()]);

        return response()->json(['data' => IncidentPresenter::present($incident, $data['latitude'] ?? null, $data['longitude'] ?? null), 'vote' => $vote]);
    }
}
