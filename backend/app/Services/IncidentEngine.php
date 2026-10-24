<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\IncidentVote;
use App\Support\GeoUtils;

class IncidentEngine
{
    public function __construct(private readonly IncidentLifecycle $lifecycle) {}

    /** Hoe ver twee meldingen uit elkaar mogen liggen om dezelfde situatie te zijn. */
    private const DUPLICATE_RADIUS_M = ['brand' => 350, 'stoffen' => 400, 'blokkade' => 250, 'verkeer' => 250, 'drukte' => 300, 'weer' => 1000];
    private const DEFAULT_DUPLICATE_RADIUS_M = 200;
    /** Een melding die recent nog bevestigd werd, telt als lopend, ook als hij ouder is. */
    private const DUPLICATE_TIME_MINUTES = 20;
    private const DUPLICATE_ACTIVE_MINUTES = 60;

    /** Related categories that should be considered duplicates of each other. */
    private const RELATED_CATEGORIES = [
        'brand' => ['brand', 'stoffen'],
        'stoffen' => ['brand', 'stoffen'],
        'verkeer' => ['verkeer', 'blokkade'],
        'blokkade' => ['verkeer', 'blokkade'],
    ];

    public function findDuplicate(string $category, float $latitude, float $longitude): ?Incident
    {
        $categories = self::RELATED_CATEGORIES[$category] ?? [$category];
        $radius = self::DUPLICATE_RADIUS_M[$category] ?? self::DEFAULT_DUPLICATE_RADIUS_M;
        $latDelta = $radius / 111_000;
        $lonDelta = $radius / (111_000 * cos(deg2rad($latitude)));

        $candidates = Incident::query()
            ->whereIn('category', $categories)
            ->where('expires_at', '>', now())
            ->whereNull('resolved_at')
            ->where(fn ($query) => $query
                ->where('created_at', '>', now()->subMinutes(self::DUPLICATE_TIME_MINUTES))
                ->orWhere('last_activity_at', '>', now()->subMinutes(self::DUPLICATE_ACTIVE_MINUTES)))
            ->whereBetween('latitude', [$latitude - $latDelta, $latitude + $latDelta])
            ->whereBetween('longitude', [$longitude - $lonDelta, $longitude + $lonDelta])
            ->get();

        return $candidates
            ->sortBy(fn (Incident $i) => GeoUtils::distanceMeters($latitude, $longitude, $i->latitude, $i->longitude))
            ->first(fn (Incident $i) => GeoUtils::distanceMeters($latitude, $longitude, $i->latitude, $i->longitude) <= $radius);
    }

    public function updateConfidence(Incident $incident): Incident
    {
        $count = $incident->confirmations_count;
        $official = $incident->source !== 'user';

        $status = $official ? 'officieel' : ($count >= 3 ? 'bevestigd' : 'gemeld');
        $visibility = $official || $count >= 2 ? 'public' : $incident->visibility;
        $confidence = $official ? 1 : min(0.90, 0.20 + ($count * 0.20));

        $incident->update([
            'status' => $status,
            'visibility' => $visibility,
            'confidence' => $confidence,
            'last_activity_at' => now(),
        ]);

        return $incident->fresh();
    }

    public function castVote(
        Incident $incident,
        int $installationId,
        string $vote,
        ?float $latitude = null,
        ?float $longitude = null,
        ?int $userId = null
    ): Incident {
        $distance = null;
        if ($latitude !== null && $longitude !== null) {
            $distance = (int) round(GeoUtils::distanceMeters(
                $latitude, $longitude, $incident->latitude, $incident->longitude
            ));
        }

        IncidentVote::create([
            'incident_id' => $incident->id,
            'installation_id' => $installationId,
            'user_id' => $userId,
            'vote' => $vote,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'distance_m' => $distance,
        ]);

        if ($vote === 'present') {
            return $this->handlePresentVote($incident);
        }

        return $this->handleResolvedVote($incident);
    }

    private function handlePresentVote(Incident $incident): Incident
    {
        $incident->increment('confirmations_count');
        $incident->update([
            'last_confirmed_at' => now(),
            'last_activity_at' => now(),
        ]);
        $this->lifecycle->touch($incident->fresh());

        return $this->updateConfidence($incident->fresh());
    }

    private function handleResolvedVote(Incident $incident): Incident
    {
        $incident->increment('resolved_count');
        $incident->update(['last_activity_at' => now()]);

        // User reports need 3 independent "niet meer aanwezig" votes to resolve
        if ($incident->source !== 'user' || $incident->fresh()->resolved_count < 3) {
            $this->lifecycle->weaken($incident->fresh());
        } else {
            $incident->update([
                'status' => 'opgelost',
                'visibility' => 'archived',
                'resolved_at' => now(),
                'expires_at' => now(),
                'archived_at' => now(),
            ]);
        }

        return $incident->fresh();
    }
}
