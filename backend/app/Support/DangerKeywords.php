<?php

namespace App\Support;

/**
 * Trefwoorden zoeken in Nederlandse meldteksten. Gewoon 'bevat', want samenstellingen
 * moeten meetellen ('beschoten', 'woningbrand', 'gasexplosie'), maar eerst gaan woorden
 * eruit die toevallig een trefwoord bevatten: plaatsnamen als Voorschoten en woorden
 * als 'geweldig'. Anders wordt een ambulancerit in Voorschoten een schietincident.
 */
class DangerKeywords
{
    private const FALSE_FRIENDS = [
        'voorschoten', 'geweldig', 'brandwijk', 'brandevoort', 'branderhorst',
        'gasthuis', 'gastenverblijf', 'overvalwagen', 'bomenkap', 'steekproef', 'steekwagen',
    ];

    public static function normalize(string $text): string
    {
        return str_replace(self::FALSE_FRIENDS, ' ', mb_strtolower($text));
    }

    public static function mentions(string $text, array $words): bool
    {
        return self::first(self::normalize($text), $words) !== null;
    }

    /** Het eerste trefwoord dat voorkomt (de volgorde van $words bepaalt de voorrang). */
    public static function first(string $normalized, array $words): ?string
    {
        foreach ($words as $word) {
            if (str_contains($normalized, $word)) {
                return $word;
            }
        }

        return null;
    }
}
