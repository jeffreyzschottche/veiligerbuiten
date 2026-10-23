<?php

namespace App\Support;

use App\Models\Incident;

/** Gedeelde weergave van een incident voor de app, de MCP-server en webhooks. */
class IncidentPresenter
{
    public const SOURCE_LABELS = [
        'ndw' => 'NDW',
        'knmi' => 'KNMI',
        'knmi_quakes' => 'KNMI',
        'luchtmeetnet' => 'Luchtmeetnet',
        'rijkswaterstaat' => 'Rijkswaterstaat',
        'politie' => 'Politie',
        'p2000' => 'P2000',
        'p2000_zwaailicht' => 'P2000',
        'nl_alert' => 'NL-Alert',
        'ns' => 'NS',
    ];

    public static function present(Incident $incident, ?float $latitude = null, ?float $longitude = null): array
    {
        $distance = $latitude !== null && $longitude !== null
            ? GeoUtils::distanceMeters($latitude, $longitude, $incident->latitude, $incident->longitude)
            : null;

        return [
            'id' => $incident->id,
            'title' => self::subtype($incident->subtype),
            'category' => $incident->category,
            'status' => $incident->status,
            'level' => DangerScale::level($incident),
            'severity' => $incident->severity,
            'confirmations' => (int) $incident->confirmations_count,
            'resolvedReports' => (int) $incident->resolved_count,
            'weight' => DangerScale::weight($incident),
            'dotScale' => DangerScale::dotScale($incident),
            'alertRadius' => DangerScale::alertRadius($incident),
            'official' => $incident->source !== 'user',
            'source' => $incident->source,
            'sourceName' => self::SOURCE_LABELS[$incident->source] ?? null,
            'description' => $incident->description,
            'geometry' => $incident->geometry,
            'latitude' => $incident->latitude,
            'longitude' => $incident->longitude,
            'distance' => $distance !== null ? (int) round($distance) : null,
            'createdAt' => $incident->created_at?->toIso8601String(),
            'updatedAt' => ($incident->last_activity_at ?? $incident->updated_at)?->toIso8601String(),
            'expiresAt' => $incident->expires_at?->toIso8601String(),
        ];
    }

    /** Subtypes zijn Nederlands opgeslagen; Engelse gebruikers krijgen een vertaling. */
    public static function subtype(?string $subtype): string
    {
        $subtype = $subtype ?: 'Melding';
        if (app()->getLocale() !== 'en') {
            return $subtype;
        }
        $map = trans('subtypes');

        return is_array($map) ? ($map[$subtype] ?? $subtype) : $subtype;
    }

    public static function distanceText(float $meters): string
    {
        return $meters < 1000
            ? (int) (round($meters / 10) * 10).' m'
            : number_format($meters / 1000, 1, app()->getLocale() === 'en' ? '.' : ',', '').' km';
    }
}
