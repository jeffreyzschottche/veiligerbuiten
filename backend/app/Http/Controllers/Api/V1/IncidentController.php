<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\Installation;
use App\Models\IncidentObservation;
use App\Services\IncidentEngine;
use App\Services\InstallationTrust;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IncidentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $latitude = $request->float('latitude', 52.3702);
        $longitude = $request->float('longitude', 4.8952);
        $radius = min(25, max(1, $request->integer('radius_km', 25)));
        $latDelta = $radius / 111;
        $lonDelta = $radius / (111 * max(.1, cos(deg2rad($latitude))));

        $incidents = Incident::query()
            ->where('visibility', 'public')->where('expires_at', '>', now())
            ->whereNull('resolved_at')
            ->where('resolved_count', '<', 3)
            ->whereBetween('latitude', [$latitude - $latDelta, $latitude + $latDelta])
            ->whereBetween('longitude', [$longitude - $lonDelta, $longitude + $lonDelta])
            ->orderByDesc('severity')
            ->latest('last_confirmed_at')
            ->get()
            ->filter(fn (Incident $incident) => $this->distanceInMeters($latitude, $longitude, $incident->latitude, $incident->longitude) <= $radius * 1000)
            ->take(100)
            ->map(fn (Incident $incident) => $this->present($incident, $latitude, $longitude));

        return response()->json(['data' => $incidents]);
    }

    public function geojson(Request $request): JsonResponse
    {
        $latitude = $request->float('latitude', 52.3702);
        $longitude = $request->float('longitude', 4.8952);
        $radius = min(25, max(1, $request->integer('radius_km', 25)));
        $latDelta = $radius / 111;
        $lonDelta = $radius / (111 * max(.1, cos(deg2rad($latitude))));
        $features = Incident::query()->where('visibility', 'public')->where('expires_at', '>', now())->whereNull('resolved_at')
            ->whereBetween('latitude', [$latitude - $latDelta, $latitude + $latDelta])
            ->whereBetween('longitude', [$longitude - $lonDelta, $longitude + $lonDelta])
            ->get()->filter(fn (Incident $incident) => $this->distanceInMeters($latitude, $longitude, $incident->latitude, $incident->longitude) <= $radius * 1000)->map(fn (Incident $incident) => [
                'type' => 'Feature',
                'id' => $incident->id,
                'geometry' => $incident->geometry ?: ['type' => 'Point', 'coordinates' => [$incident->longitude, $incident->latitude]],
                'properties' => $this->present($incident, $latitude, $longitude),
            ]);
        return response()->json(['type' => 'FeatureCollection', 'features' => $features]);
    }

    public function show(Request $request, Incident $incident): JsonResponse
    {
        abort_if($incident->visibility !== 'public' || $incident->expires_at->isPast() || $incident->resolved_at, 404);
        return response()->json(['data' => $this->present($incident, $request->float('latitude', $incident->latitude), $request->float('longitude', $incident->longitude))]);
    }

    public function confirm(Request $request, Incident $incident, IncidentEngine $engine, InstallationTrust $trust): JsonResponse
    {
        return $this->vote($request, $incident, 'present', $engine, $trust);
    }

    public function resolve(Request $request, Incident $incident, IncidentEngine $engine, InstallationTrust $trust): JsonResponse
    {
        return $this->vote($request, $incident, 'resolved', $engine, $trust);
    }

    private function present(Incident $incident, float $latitude, float $longitude): array
    {
        $distance = $this->distanceInMeters($latitude, $longitude, $incident->latitude, $incident->longitude);
        $left = min(82, max(18, 50 + (($incident->longitude - $longitude) * 480)));
        $top = min(78, max(24, 54 - (($incident->latitude - $latitude) * 720)));

        return [
            'id' => $incident->id,
            'title' => $incident->subtype,
            'subtype' => $incident->subtype,
            'category' => $incident->category,
            'status' => $incident->status,
            'source' => in_array($incident->source, ['ndw', 'knmi', 'luchtmeetnet', 'rijkswaterstaat', 'politie']) ? 'official' : $incident->source,
            'sourceName' => strtoupper($incident->source),
            'severity' => $incident->severity,
            'confidence' => $incident->confidence,
            'geometry' => $incident->geometry,
            'distance' => $distance < 1000 ? round($distance) . ' m' : number_format($distance / 1000, 1, ',', '') . ' km',
            'reportedAt' => $incident->created_at->diffForHumans(),
            'description' => $incident->description ?: 'Melding uit de omgeving.',
            'advice' => 'Blijf op veilige afstand en bel 112 bij direct gevaar.',
            'confirmations' => $incident->confirmations_count,
            'resolvedReports' => $incident->resolved_count,
            'latitude' => $incident->latitude,
            'longitude' => $incident->longitude,
            'left' => round($left),
            'top' => round($top),
        ];
    }

    private function distanceInMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earth = 6371000;
        $lat = deg2rad($lat2 - $lat1);
        $lon = deg2rad($lon2 - $lon1);
        $a = sin($lat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lon / 2) ** 2;
        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
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
        return response()->json(['data' => $this->present($incident, $data['latitude'] ?? $incident->latitude, $data['longitude'] ?? $incident->longitude), 'vote' => $vote]);
    }
}
