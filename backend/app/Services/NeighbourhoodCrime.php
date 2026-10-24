<?php

namespace App\Services;

use App\Models\NeighbourhoodRisk;
use App\Services\Sources\Geocoder;
use App\Services\Sources\PoliteFetcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Buurtcijfers van de politie (CBS-tabel 47022NED, maandcijfers per buurt, CC-BY 4.0).
 *
 * Telt over de laatste twaalf maanden geweld en straatroof per buurt en zet dat af
 * tegen het landelijke gemiddelde per km². Alleen buurten die er ver bovenuit steken
 * (en waar het om genoeg misdrijven gaat) worden een plek in de app.
 *
 * Bewust geen meldingen: het zijn geregistreerde misdrijven van het afgelopen jaar,
 * geen actueel gevaar. De app toont ze rustig als buurtcijfer, nooit met een pushbericht.
 */
class NeighbourhoodCrime
{
    public function __construct(private readonly Geocoder $geocoder) {}

    /** @return array{count: int, from: string, to: string} */
    public function refresh(): array
    {
        $config = config('veiligbuiten.neighbourhood_stats');
        $periods = $this->periods($config['months']);
        [$from, $to] = [$periods[0], end($periods)];

        $counts = [];
        $national = [];
        foreach ($config['types'] as $code => [$name]) {
            $filter = sprintf("SoortMisdrijf eq '%s ' and Perioden ge '%s' and Perioden le '%s'", $code, $from, $to);
            foreach ($this->rows(config('services.cbs.crime_feed').'/TypedDataSet', $filter, 'WijkenEnBuurten,GeregistreerdeMisdrijven_1') as $row) {
                $region = trim($row['WijkenEnBuurten']);
                $value = (int) ($row['GeregistreerdeMisdrijven_1'] ?? 0);
                if ($region === 'NL00') {
                    $national[$name] = ($national[$name] ?? 0) + $value;
                } elseif (str_starts_with($region, 'BU') && $value > 0) {
                    $counts[$region][$name] = ($counts[$region][$name] ?? 0) + $value;
                }
            }
        }

        $areas = $this->areas((int) substr($to, 0, 4));
        $weights = collect($config['types'])->mapWithKeys(fn (array $type) => [$type[0] => $type[1]]);
        $weighted = fn (array $c) => $weights->map(fn (int $weight, string $name) => $weight * ($c[$name] ?? 0))->sum();
        $nationalDensity = $weighted($national) / max(1, ($areas['NL00'] ?? 0) / 100);
        if ($nationalDensity <= 0) {
            throw new \RuntimeException('Geen landelijke cijfers of oppervlakte van het CBS ontvangen.');
        }

        $rows = [];
        foreach ($counts as $code => $byType) {
            $hectares = $areas[$code] ?? 0;
            $total = array_sum($byType);
            if ($hectares <= 0 || $total < $config['min_count']) continue;
            $ratio = $weighted($byType) / ($hectares / 100) / $nationalDensity;
            if ($ratio < $config['min_ratio']) continue;
            $point = $this->geocoder->neighbourhood($code);
            if (! $point) continue;
            $rows[] = [
                'code' => $code,
                'name' => mb_substr($point['name'] ?: $code, 0, 120),
                'place' => mb_substr($point['place'] ?? '', 0, 80) ?: null,
                'latitude' => round($point['latitude'], 7),
                'longitude' => round($point['longitude'], 7),
                // Een cirkel met de oppervlakte van de buurt, binnen redelijke grenzen.
                'radius_m' => (int) max(150, min(700, round(sqrt($hectares * 10_000 / M_PI)))),
                'counts' => json_encode($byType),
                'total' => min(65_535, $total),
                'ratio' => round(min(9_999_999, $ratio), 1),
                'period_from' => $from,
                'period_to' => $to,
                'computed_at' => now(),
            ];
        }

        DB::transaction(function () use ($rows) {
            NeighbourhoodRisk::query()->delete();
            foreach (array_chunk($rows, 200) as $chunk) NeighbourhoodRisk::query()->insert($chunk);
        });
        AreaSync::bump();

        return ['count' => count($rows), 'from' => $from, 'to' => $to];
    }

    /** De laatste N maanden die het CBS heeft, bijvoorbeeld ['2025MM10', …, '2026MM09']. @return list<string> */
    private function periods(int $months): array
    {
        $keys = collect($this->get(config('services.cbs.crime_feed').'/Perioden', ['$format' => 'json'])['value'] ?? [])
            ->pluck('Key')->filter(fn ($key) => preg_match('/^\d{4}MM\d{2}$/', (string) $key))->sort()->values();
        if ($keys->isEmpty()) {
            throw new \RuntimeException('Het CBS gaf geen maandperiodes terug.');
        }

        return $keys->slice(-$months)->values()->all();
    }

    /**
     * Landoppervlakte (ha) per buurt uit de Kerncijfers wijken en buurten van hetzelfde jaar,
     * zodat de buurtcodes overeenkomen. Is dat jaar nog niet compleet, dan het jaar ervoor.
     *
     * @return array<string, int>
     */
    private function areas(int $year): array
    {
        foreach ([$year, $year - 1] as $candidate) {
            $table = $this->kerncijfersTable($candidate);
            if (! $table) continue;
            $property = $this->property($table, 'OppervlakteLand');
            if (! $property) continue;
            $areas = [];
            foreach ($this->rows(config('services.cbs.feed_base')."/{$table}/TypedDataSet", null, "WijkenEnBuurten,{$property}") as $row) {
                $region = trim($row['WijkenEnBuurten']);
                if (($region === 'NL00' || str_starts_with($region, 'BU')) && is_numeric($row[$property] ?? null)) {
                    $areas[$region] = (int) $row[$property];
                }
            }
            if (count($areas) > 1000) return $areas;
        }

        throw new \RuntimeException("Geen buurtoppervlaktes gevonden voor {$year}.");
    }

    private function kerncijfersTable(int $year): ?string
    {
        $tables = $this->get(config('services.cbs.catalog'), [
            '$format' => 'json',
            '$filter' => "Title eq 'Kerncijfers wijken en buurten {$year}'",
            '$select' => 'Identifier',
        ])['value'] ?? [];

        return $tables[0]['Identifier'] ?? null;
    }

    /** De kolomnamen krijgen per jaar een ander volgnummer (OppervlakteLand_116, _117…). */
    private function property(string $table, string $prefix): ?string
    {
        $base = str_replace('/ODataFeed/', '/ODataApi/', config('services.cbs.feed_base'));
        $properties = $this->get("{$base}/{$table}/DataProperties", ['$format' => 'json'])['value'] ?? [];

        return collect($properties)->pluck('Key')->first(fn ($key) => is_string($key) && str_starts_with($key, $prefix.'_'));
    }

    /** Alle rijen, pagina voor pagina (de feed geeft er 10.000 per keer). @return \Generator<int, array> */
    private function rows(string $url, ?string $filter, string $select): \Generator
    {
        $query = array_filter(['$format' => 'json', '$filter' => $filter, '$select' => $select]);
        $seen = [];
        for ($page = 0; $url && $page < 100; $page++) {
            $body = $this->get($url, $query);
            yield from $body['value'] ?? [];
            $url = $body['odata.nextLink'] ?? null;
            // Nooit dezelfde pagina twee keer: liever stoppen dan eindeloos dezelfde rijen tellen.
            if ($url && isset($seen[$url])) throw new \RuntimeException('CBS gaf dezelfde vervolgpagina opnieuw.');
            $seen[(string) $url] = true;
            $query = null; // de volgende link bevat de query al (een lege array zou die wissen)
        }
    }

    private function get(string $url, ?array $query): array
    {
        return Http::timeout(120)->retry(3, 2000)->acceptJson()
            ->withHeaders(['User-Agent' => PoliteFetcher::USER_AGENT])
            ->get($url, $query)->throw()->json() ?? [];
    }
}
