<?php

namespace App\Services;

use App\Models\AlertDelivery;
use App\Models\Incident;
use App\Models\Installation;
use App\Support\GeoUtils;

class AlertEngine
{
    private const DISTANCE_LIMITS = [
        'direct' => 5000,
        'warning' => 3000,
        'info' => 1000,
    ];

    public function queueFor(Incident $incident): int
    {
        if (!$this->shouldAlert($incident)) {
            return 0;
        }

        $level = $this->alertLevel($incident);
        $eventKey = $incident->status . ':' . ($incident->last_activity_at?->timestamp ?? now()->timestamp);
        $count = 0;

        $this->eligibleInstallations()->each(
            function (Installation $installation) use ($incident, $level, $eventKey, &$count) {
                if (!$this->wantsLevel($installation, $level)) {
                    return;
                }

                $distance = GeoUtils::distanceMeters(
                    $installation->last_latitude,
                    $installation->last_longitude,
                    $incident->latitude,
                    $incident->longitude
                );

                if ($distance > self::DISTANCE_LIMITS[$level]) {
                    return;
                }

                $delivery = AlertDelivery::firstOrCreate([
                    'incident_id' => $incident->id,
                    'installation_id' => $installation->id,
                    'event_key' => $eventKey,
                ], [
                    'level' => $level,
                    'status' => 'queued',
                    'reason' => 'relevant_' . $level,
                    'payload' => $this->payload($incident, $distance),
                ]);

                if ($delivery->wasRecentlyCreated) {
                    $count++;
                }
            }
        );

        return $count;
    }

    private function shouldAlert(Incident $incident): bool
    {
        return in_array($incident->status, ['bevestigd', 'officieel'], true)
            && $incident->confidence >= 0.60;
    }

    private function alertLevel(Incident $incident): string
    {
        if ($incident->severity >= 4) return 'direct';
        if ($incident->severity >= 3) return 'warning';
        return 'info';
    }

    private function eligibleInstallations()
    {
        return Installation::query()
            ->whereNotNull('push_token')
            ->whereNull('blocked_at')
            ->whereNotNull('last_latitude')
            ->whereNotNull('last_longitude')
            ->where('last_location_at', '>', now()->subDay());
    }

    private function wantsLevel(Installation $installation, string $level): bool
    {
        return ($installation->alert_preferences[$level] ?? false) === true;
    }

    private function payload(Incident $incident, float $distance): array
    {
        $distanceText = $distance < 1000
            ? round($distance) . ' m'
            : number_format($distance / 1000, 1, ',', '') . ' km';

        return [
            'title' => $incident->subtype . ' op ' . $distanceText,
            'body' => $incident->source !== 'user'
                ? 'Officiële bron. Bekijk de actuele situatie.'
                : 'Bevestigde melding in je omgeving.',
            'data' => [
                'incident_id' => (string) $incident->id,
                'latitude' => (string) $incident->latitude,
                'longitude' => (string) $incident->longitude,
            ],
        ];
    }
}
