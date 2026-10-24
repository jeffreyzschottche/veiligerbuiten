<?php

namespace App\Support;

use App\Models\Incident;

/**
 * Eén bron van waarheid voor ernst, stipgrootte en meldingsbereik.
 *
 * Gewicht = bevestigingen (+ vast gewicht voor officiële bronnen). Bereik en
 * stipgrootte groeien logaritmisch met dat gewicht en zijn begrensd, zodat een
 * melding met veel bevestigingen iets verder reikt maar nooit absurd groot wordt.
 */
class DangerScale
{
    public const LEVELS = ['info', 'warn', 'danger'];

    public static function level(Incident $incident): string
    {
        return match (true) {
            $incident->severity >= 4 => 'danger',
            $incident->severity >= 3 => 'warn',
            default => 'info',
        };
    }

    public static function weight(Incident $incident): int
    {
        $official = $incident->source !== 'user' ? config('veiligbuiten.danger_scale.official_weight') : 0;

        return max(1, (int) $incident->confirmations_count + $official);
    }

    public static function alertRadius(Incident $incident): int
    {
        $config = config('veiligbuiten.danger_scale');
        $base = $config['base_radius_m'][self::level($incident)];
        $factor = min($config['max_radius_factor'], 1 + 0.5 * log(1 + self::weight($incident), 2) - 0.5);

        // Een waarschuwing voor een gebied (NL-Alert) reikt minstens tot de rand van dat gebied.
        return max((int) round($base * max(1, $factor)), self::areaRadius($incident));
    }

    /** Grootste afstand van het middelpunt tot de rand van een polygoon, begrensd op 30 km. */
    public static function areaRadius(Incident $incident): int
    {
        $geometry = $incident->geometry;
        if (($geometry['type'] ?? null) !== 'Polygon' || empty($geometry['coordinates'][0])) {
            return 0;
        }
        $furthest = 0.0;
        foreach ($geometry['coordinates'][0] as [$longitude, $latitude]) {
            $furthest = max($furthest, GeoUtils::distanceMeters($incident->latitude, $incident->longitude, (float) $latitude, (float) $longitude));
        }

        return (int) min(30_000, round($furthest));
    }

    /** 1.0 voor één melding, oplopend tot max_dot_scale. */
    public static function dotScale(Incident $incident): float
    {
        $max = config('veiligbuiten.danger_scale.max_dot_scale');

        return round(min($max, 1 + log(self::weight($incident), 2) / 4), 2);
    }

    public static function rank(string $level): int
    {
        return array_search($level, self::LEVELS, true) ?: 0;
    }
}
