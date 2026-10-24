<?php

namespace App\Support;

class CategoryConfig
{
    /** All incident categories with their metadata. */
    public const CATEGORIES = [
        'brand' => [
            'label' => 'Brand',
            'icon' => 'fire',
            'color' => '#ef4444',
            'incident_type' => 'fire',
            'default_severity' => 4,
            'default_hours' => 2,
        ],
        'geweld' => [
            'label' => 'Geweld',
            'icon' => 'alert-triangle',
            'color' => '#dc2626',
            'incident_type' => 'violence',
            'default_severity' => 5,
            'default_hours' => 2,
        ],
        // Intimidatie op straat: handtastelijkheden, nafluiten, volgen. Vooral voor vrouwen een
        // reden om zich onveilig te voelen; daarom een eigen categorie, eigen filter en eigen meldtypes.
        'intimidatie' => [
            'label' => 'Intimidatie',
            'icon' => 'hand',
            'color' => '#db2777',
            'incident_type' => 'harassment',
            'default_severity' => 3,
            'default_hours' => 1,
        ],
        'onveilig' => [
            'label' => 'Onveilig',
            'icon' => 'alert-circle',
            'color' => '#f97316',
            'incident_type' => 'emergency',
            'default_severity' => 3,
            'default_hours' => 2,
        ],
        'verkeer' => [
            'label' => 'Verkeer',
            'icon' => 'car',
            'color' => '#eab308',
            'incident_type' => 'traffic',
            'default_severity' => 3,
            'default_hours' => 2,
        ],
        'blokkade' => [
            'label' => 'Blokkade',
            'icon' => 'road-barrier',
            'color' => '#f59e0b',
            'incident_type' => 'traffic',
            'default_severity' => 3,
            'default_hours' => 4,
        ],
        'drukte' => [
            'label' => 'Drukte',
            'icon' => 'users',
            'color' => '#8b5cf6',
            'incident_type' => 'crowd',
            'default_severity' => 2,
            'default_hours' => 4,
        ],
        'weer' => [
            'label' => 'Weer',
            'icon' => 'cloud-lightning',
            'color' => '#3b82f6',
            'incident_type' => 'weather',
            'default_severity' => 3,
            'default_hours' => 6,
        ],
        'stoffen' => [
            'label' => 'Stoffen',
            'icon' => 'wind',
            'color' => '#10b981',
            'incident_type' => 'air_quality',
            'default_severity' => 3,
            'default_hours' => 2,
        ],
        'dier' => [
            'label' => 'Dier',
            'icon' => 'bug',
            'color' => '#84cc16',
            'incident_type' => 'animal',
            'default_severity' => 2,
            'default_hours' => 4,
        ],
        'anders' => [
            'label' => 'Anders',
            'icon' => 'info',
            'color' => '#6b7280',
            'incident_type' => 'other',
            'default_severity' => 2,
            'default_hours' => 2,
        ],
    ];

    /** Status labels and their visibility rules. */
    public const STATUSES = [
        'gemeld' => ['label' => 'Gemeld', 'public' => false],
        'bevestigd' => ['label' => 'Bevestigd', 'public' => true],
        'officieel' => ['label' => 'Officieel', 'public' => true],
        'opgelost' => ['label' => 'Opgelost', 'public' => false],
        'verlopen' => ['label' => 'Verlopen', 'public' => false],
    ];

    public static function get(string $category): array
    {
        return self::CATEGORIES[$category] ?? self::CATEGORIES['anders'];
    }

    public static function color(string $category): string
    {
        return self::get($category)['color'];
    }

    public static function icon(string $category): string
    {
        return self::get($category)['icon'];
    }

    public static function incidentType(string $category): string
    {
        return self::get($category)['incident_type'];
    }

    public static function defaultSeverity(string $category): int
    {
        return self::get($category)['default_severity'];
    }

    public static function defaultHours(string $category): int
    {
        return self::get($category)['default_hours'];
    }

    public static function all(): array
    {
        return self::CATEGORIES;
    }

    public static function keys(): array
    {
        return array_keys(self::CATEGORIES);
    }
}
