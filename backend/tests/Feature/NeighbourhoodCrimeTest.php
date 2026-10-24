<?php

namespace Tests\Feature;

use App\Models\NeighbourhoodRisk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class NeighbourhoodCrimeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * CBS nagebootst. Nederland: 3.364.723 ha. Leidsebuurt (10 ha) heeft veel straatroof,
     * Rustoord (10 ha) een paar incidenten, Grootveld (5.000 ha) veel in absolute zin maar
     * verspreid over een enorm gebied. De straatroof komt in twee pagina's.
     */
    private function fakeCbs(): void
    {
        $crime = [
            '1.4.6' => [['NL00', 2770], ['BU0363AH02', 30], ['BU99990001', 1], ['BU99990002', 40]],
            '1.4.5' => [['NL00', 40000], ['BU0363AH02', 60], ['BU99990001', 3], ['BU99990002', 80]],
        ];
        Http::fake(function (Request $request) use ($crime) {
            $url = urldecode($request->url());

            return match (true) {
                str_contains($url, '47022NED/Perioden') => Http::response(['value' => array_map(fn ($m) => ['Key' => $m], [...$this->months(), '2026JJ00'])]),
                str_contains($url, '47022NED/TypedDataSet') && str_contains($url, '$skip=1') => Http::response(['value' => [['WijkenEnBuurten' => 'BU0363AH02', 'GeregistreerdeMisdrijven_1' => 12]]]),
                str_contains($url, '47022NED/TypedDataSet') => $this->crimePage($url, $crime),
                str_contains($url, 'ODataCatalog') => Http::response(['value' => str_contains($url, '2026') ? [['Identifier' => '86358NED']] : []]),
                str_contains($url, '86358NED/DataProperties') => Http::response(['value' => [['Key' => 'AantalInwoners_5'], ['Key' => 'OppervlakteLand_117']]]),
                str_contains($url, '86358NED/TypedDataSet') => Http::response(['value' => array_merge(
                    [['WijkenEnBuurten' => 'NL00      ', 'OppervlakteLand_117' => 3364723], ['WijkenEnBuurten' => 'BU0363AH02', 'OppervlakteLand_117' => 10], ['WijkenEnBuurten' => 'BU99990001', 'OppervlakteLand_117' => 10], ['WijkenEnBuurten' => 'BU99990002', 'OppervlakteLand_117' => 5000]],
                    array_map(fn ($i) => ['WijkenEnBuurten' => sprintf('BU8888%04d', $i), 'OppervlakteLand_117' => 20], range(1, 1000)),
                )]),
                str_contains($url, 'api.pdok.nl') && str_contains($url, 'BU0363AH02') => Http::response(['response' => ['docs' => [['buurtnaam' => 'Leidsebuurt-Noordoost', 'gemeentenaam' => 'Amsterdam', 'centroide_ll' => 'POINT(4.88528 52.36336)']]]]),
                default => Http::response(['value' => []]),
            };
        });
    }

    /** 2025MM09 t/m 2026MM09: dertien maanden, dus de oudste valt buiten de laatste twaalf. @return list<string> */
    private function months(): array
    {
        return array_map(fn ($i) => sprintf('%dMM%02d', 2025 + intdiv(8 + $i, 12), (8 + $i) % 12 + 1), range(0, 12));
    }

    private function crimePage(string $url, array $crime)
    {
        $this->assertStringContainsString("Perioden ge '2025MM10' and Perioden le '2026MM09'", $url, 'the last twelve months the CBS has, by month');
        preg_match("/SoortMisdrijf eq '([\d.]+) '/", $url, $type);
        $rows = array_map(fn ($row) => ['WijkenEnBuurten' => str_pad($row[0], 10), 'GeregistreerdeMisdrijven_1' => $row[1]], $crime[$type[1]] ?? []);
        $next = $type[1] === '1.4.6' ? ['odata.nextLink' => 'https://dataderden.cbs.nl/ODataFeed/OData/47022NED/TypedDataSet?$format=json&$skip=10000'] : [];

        return Http::response(['value' => $rows] + $next);
    }

    public function test_only_neighbourhoods_far_above_the_national_density_become_a_spot(): void
    {
        $this->fakeCbs();

        $this->artisan('veiligbuiten:neighbourhood-crime')->expectsOutputToContain('1 buurten (2025MM10 t/m 2026MM09)')->assertSuccessful();

        $spot = NeighbourhoodRisk::sole();
        $this->assertSame('Leidsebuurt-Noordoost', $spot->name);
        $this->assertSame(['straatroof' => 42, 'mishandeling' => 60], $spot->counts, 'both pages of street robbery are counted');
        $this->assertSame(102, $spot->total);
        $this->assertSame(178, $spot->radius_m, 'a circle with the area of the neighbourhood');
    }

    public function test_statistics_reach_phones_after_the_reported_spots_and_change_the_etag(): void
    {
        $this->actingAsSubscriber();
        $first = $this->getJson('/api/v1/incidents/sync?latitude=52.37&longitude=4.89')->assertOk()->assertJsonCount(0, 'hotspots');

        NeighbourhoodRisk::create(['code' => 'BU0363AH02', 'name' => 'Leidsebuurt-Noordoost', 'place' => 'Amsterdam', 'latitude' => 52.36336, 'longitude' => 4.88528, 'radius_m' => 178, 'counts' => ['straatroof' => 42], 'total' => 102, 'ratio' => 1300, 'period_from' => '2025MM10', 'period_to' => '2026MM09', 'computed_at' => now()]);

        $this->getJson('/api/v1/incidents/sync?latitude=52.37&longitude=4.89', ['If-None-Match' => $first->headers->get('ETag')])
            ->assertOk()
            ->assertJsonPath('hotspots.0.kind', 'stats')
            ->assertJsonPath('hotspots.0.name', 'Leidsebuurt-Noordoost')
            ->assertJsonPath('hotspots.0.counts.straatroof', 42);
    }

    public function test_a_failing_cbs_keeps_last_months_neighbourhoods(): void
    {
        NeighbourhoodRisk::create(['code' => 'BU0363AH02', 'name' => 'Leidsebuurt-Noordoost', 'latitude' => 52.36, 'longitude' => 4.88, 'radius_m' => 178, 'counts' => [], 'total' => 102, 'ratio' => 1300, 'period_from' => '2025MM09', 'period_to' => '2026MM08', 'computed_at' => now()]);
        Http::fake(['*' => Http::response('', 503)]);
        Sleep::fake();

        try {
            $this->artisan('veiligbuiten:neighbourhood-crime');
        } catch (\Throwable) {
        }

        $this->assertSame(1, NeighbourhoodRisk::count());
    }
}
