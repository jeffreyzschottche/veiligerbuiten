<?php

namespace App\Support;

/**
 * Uitknoppen voor bronnen die we niet als open data mogen gebruiken, maar op basis
 * van toestemming of zonder duidelijke licentie (zie config veiligbuiten.source_switches).
 * Uit is helemaal uit: niet meer ophalen, bestaande meldingen gearchiveerd, geen push,
 * geen samenvoeging met andere bronnen en 'uitgeschakeld' in de bronnenlijst.
 */
final class SourceSwitch
{
    /** Bronnen zonder uitknop (open overheidsdata) staan altijd aan. */
    public static function enabled(string $source): bool
    {
        return (bool) config("veiligbuiten.source_switches.{$source}", true);
    }

    /** @return list<string> */
    public static function disabled(): array
    {
        return array_keys(array_filter(config('veiligbuiten.source_switches', []), fn ($on) => ! $on));
    }
}
