<?php

namespace App\Support;

use App\Models\Incident;

/**
 * Eén bron van waarheid voor ernst, stipgrootte en meldingsbereik.
 *
 * Het niveau begint bij de ernst van de situatie en wordt daarna bijgesteld
 * met wat we weten over hoe zeker en hoe actueel het is:
 *
 *  - één losse, onbevestigde gebruikersmelding is nooit meteen 'gevaar';
 *  - meer 'niet meer aanwezig'-stemmen dan bevestigingen → één niveau lager;
 *  - verouderd (versheid onder een drempel) → één niveau lager;
 *  - meerdere onafhankelijke signalen op één plek (cluster) → één niveau hoger.
 *
 * Het bereik hangt af van de categorie (een gaswolk reikt verder dan een
 * vechtpartij), groeit logaritmisch met bevestigingen en krimpt als een
 * melding veroudert. Gebiedswaarschuwingen reiken altijd tot hun rand.
 */
class DangerScale
{
    public const LEVELS = ['info', 'warn', 'danger'];

    /** Bronnen met een eigen, expliciete eindtijd: die verouderen niet tussentijds. */
    private const SELF_TIMED_SOURCES = ['nl_alert', 'knmi'];

    private const STALE_FRESHNESS = 0.35;

    public static function level(Incident $incident): string
    {
        $rank = match (true) {
            $incident->severity >= 4 => 2,
            $incident->severity >= 3 => 1,
            default => 0,
        };
        if ($rank === 2 && $incident->source === 'user' && ! self::corroborated($incident)) {
            $rank = 1;
        }
        if ($incident->resolved_count > 0 && $incident->resolved_count >= max(1, (int) $incident->confirmations_count)) {
            $rank--;
        }
        if (self::freshness($incident) < self::STALE_FRESHNESS) {
            $rank--;
        }
        if (self::cluster($incident) >= 2) {
            $rank++;
        }

        return self::LEVELS[max(0, min(2, $rank))];
    }

    /** Bevestigd door meerdere mensen, of er is een officieel signaal vlakbij. */
    public static function corroborated(Incident $incident): bool
    {
        return $incident->source !== 'user'
            || $incident->confirmations_count >= 2
            || (bool) data_get($incident->risk_flags, 'official_nearby', false);
    }

    /** Aantal andere ernstige, actuele signalen binnen de clusterstraal. */
    public static function cluster(Incident $incident): int
    {
        return (int) data_get($incident->risk_flags, 'cluster', 0);
    }

    /** 1,0 = net gezien; halveert per halfwaardetijd van de categorie sinds de laatste bevestiging. */
    public static function freshness(Incident $incident): float
    {
        if (in_array($incident->source, self::SELF_TIMED_SOURCES, true)) {
            return 1.0;
        }
        $seen = $incident->last_confirmed_at ?? $incident->created_at;
        if (! $seen) {
            return 1.0;
        }
        $rules = config('veiligbuiten.lifecycle.categories');
        $halfLife = ($rules[$incident->category] ?? $rules['anders'])['half_life'];
        $minutes = max(0, $seen->diffInSeconds(now(), false) / 60);

        return round(0.5 ** ($minutes / $halfLife), 3);
    }

    /** Hoe zeker we zijn dat het klopt (0–1). */
    public static function confidence(Incident $incident): float
    {
        if ($incident->source !== 'user') {
            // P2000 wordt met vaste regels uit een pagerbericht afgeleid; iets minder zeker dan een officiële waarschuwing.
            $base = str_starts_with((string) $incident->source, 'p2000') ? 0.85 : 1.0;
        } else {
            $base = 1 - 0.6 ** max(1, (int) $incident->confirmations_count);
        }
        $confirmations = max(1, (int) $incident->confirmations_count);
        $doubt = $confirmations / ($confirmations + (int) $incident->resolved_count);
        $boost = min(0.15, 0.05 * self::cluster($incident));

        return round(max(0.05, min($incident->source === 'user' ? 0.97 : 1.0, $base * $doubt + $boost)), 2);
    }

    public static function weight(Incident $incident): int
    {
        $official = $incident->source !== 'user' ? config('veiligbuiten.danger_scale.official_weight') : 0;

        return max(1, (int) $incident->confirmations_count + $official);
    }

    public static function alertRadius(Incident $incident): int
    {
        $config = config('veiligbuiten.danger_scale');
        $level = self::level($incident);
        $base = ($config['category_radius_m'][$incident->category] ?? $config['category_radius_m']['anders']) * $config['level_factor'][$level];
        $growth = min($config['max_radius_factor'], max(1, 0.5 + 0.5 * log(1 + self::weight($incident), 2)));
        $fresh = max($config['min_freshness_factor'], self::freshness($incident));

        return max(100, (int) round($base * $growth * $fresh), self::areaRadius($incident));
    }

    /** Grootste afstand van het middelpunt tot de rand van een gebied (polygoon of cirkel). */
    public static function areaRadius(Incident $incident): int
    {
        $geometry = $incident->geometry;
        $max = (int) config('veiligbuiten.danger_scale.max_area_radius_m');
        if (($geometry['type'] ?? null) === 'Circle') {
            return (int) min($max, (int) ($geometry['radius_m'] ?? 0));
        }
        if (($geometry['type'] ?? null) !== 'Polygon' || empty($geometry['coordinates'][0])) {
            return 0;
        }
        $furthest = 0.0;
        foreach ($geometry['coordinates'][0] as [$longitude, $latitude]) {
            $furthest = max($furthest, GeoUtils::distanceMeters($incident->latitude, $incident->longitude, (float) $latitude, (float) $longitude));
        }

        return (int) min($max, round($furthest));
    }

    /** 1.0 voor één melding, oplopend tot max_dot_scale. */
    public static function dotScale(Incident $incident): float
    {
        $max = config('veiligbuiten.danger_scale.max_dot_scale');

        return round(min($max, 1 + log(self::weight($incident), 2) / 4), 2);
    }

    /** Sorteerwaarde 0–100: ernst × zekerheid × versheid. */
    public static function score(Incident $incident): int
    {
        return (int) round(min(5, (int) $incident->severity) / 5 * self::confidence($incident) * max(0.2, self::freshness($incident)) * 100);
    }

    public static function rank(string $level): int
    {
        return array_search($level, self::LEVELS, true) ?: 0;
    }
}
