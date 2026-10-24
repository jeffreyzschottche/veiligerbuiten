<?php

namespace App\Services\Sources;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Plaatst meldingen zonder coördinaten op de kaart via de PDOK Locatieserver
 * (overheid, gratis, zonder sleutel).
 *
 * Bewust nooit op huisnummer: hoogstens een straat of een postcode, en een
 * ambulancerit wordt daarna nog grof afgerond (zie roundForPrivacy). Elke
 * uitkomst wordt 30 dagen bewaard, dus dezelfde straat kost PDOK maar één verzoek.
 */
class Geocoder
{
    private const URL = 'https://api.pdok.nl/bzk/locatieserver/search/v3_1/free';
    private const TTL_DAYS = 30;
    /** ~300 m: genoeg om te weten in welke buurt, te grof om een woning aan te wijzen. */
    private const PRIVACY_GRID = 0.003;

    /** @return array{latitude: float, longitude: float, precision_m: int}|null */
    public function street(string $street, string $city): ?array
    {
        $street = $this->clean($street);
        $city = $this->clean($city);
        if ($street === '' || $city === '') {
            return $city !== '' ? $this->city($city) : null;
        }

        return $this->lookup("{$street} {$city}", 'weg', $city, 250) ?? $this->city($city);
    }

    /** @return array{latitude: float, longitude: float, precision_m: int}|null */
    public function postcode(string $postcode): ?array
    {
        $postcode = strtoupper(str_replace(' ', '', $postcode));
        if (! preg_match('/^\d{4}[A-Z]{2}$/', $postcode)) {
            return null;
        }

        return $this->lookup("postcode:{$postcode}", 'postcode', null, 150);
    }

    /** @return array{latitude: float, longitude: float, precision_m: int}|null */
    public function city(string $city): ?array
    {
        $city = $this->clean($city);

        return $city === '' ? null : $this->lookup($city, 'woonplaats', $city, 2500);
    }

    /** @param array{latitude: float, longitude: float, precision_m: int} $point */
    public function roundForPrivacy(array $point): array
    {
        $round = fn (float $value) => round(round($value / self::PRIVACY_GRID) * self::PRIVACY_GRID, 6);

        return ['latitude' => $round($point['latitude']), 'longitude' => $round($point['longitude']), 'precision_m' => max(300, $point['precision_m']), 'place' => $point['place'] ?? ''];
    }

    /**
     * @param  string|null  $mustMatchCity  de gevonden woonplaats moet deze naam bevatten (tegen verkeerde treffers)
     * @return array{latitude: float, longitude: float, precision_m: int}|null
     */
    private function lookup(string $query, string $type, ?string $mustMatchCity, int $precision): ?array
    {
        // v2: met plaatsnaam; oudere cache-items hadden die nog niet.
        $key = 'vb:geo2:'.sha1("{$type}|".mb_strtolower($query));
        $cached = Cache::get($key);
        if ($cached !== null) {
            return $cached ?: null;
        }

        try {
            $doc = Http::timeout(6)->acceptJson()->withHeaders(['User-Agent' => PoliteFetcher::USER_AGENT])
                ->get(self::URL, ['q' => $query, 'fq' => "type:{$type}", 'rows' => 1, 'fl' => 'weergavenaam,woonplaatsnaam,centroide_ll'])
                ->throw()->json('response.docs.0');
        } catch (\Throwable) {
            return null; // niet cachen: volgende keer opnieuw proberen
        }

        $point = null;
        if ($doc && preg_match('/POINT\(([\d.]+) ([\d.]+)\)/', (string) ($doc['centroide_ll'] ?? ''), $match)
            && (! $mustMatchCity || str_contains($this->clean((string) ($doc['woonplaatsnaam'] ?? $doc['weergavenaam'] ?? '')), $mustMatchCity))) {
            $point = ['latitude' => (float) $match[2], 'longitude' => (float) $match[1], 'precision_m' => $precision, 'place' => (string) ($doc['woonplaatsnaam'] ?? '')];
        }
        // Ook 'niet gevonden' bewaren (als false), zodat we PDOK niet blijven vragen.
        Cache::put($key, $point ?? false, now()->addDays(self::TTL_DAYS));

        return $point;
    }

    /** Feeds gebruiken de gewone naam, PDOK de officiële. */
    private const CITY_ALIASES = ['den haag' => "'s-gravenhage", 'den bosch' => "'s-hertogenbosch"];

    private function clean(string $value): string
    {
        $value = trim(preg_replace('/\s+/', ' ', mb_strtolower(str_replace('’', "'", $value))));

        return self::CITY_ALIASES[$value] ?? $value;
    }
}
