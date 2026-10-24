<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Support\DangerScale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SourcesTest extends TestCase
{
    use RefreshDatabase;

    private function nlAlert(array $overrides = []): array
    {
        $ring = '51.930,4.420 51.940,4.420 51.940,4.440 51.930,4.440 51.930,4.420';

        return ['id' => 'abc123', 'type' => 'alert', 'message' => 'Brand met rook in Spaanse Polder Rotterdam. Doe ramen en deuren dicht.', 'start_at' => now()->subMinutes(5)->toIso8601String(), 'stop_at' => now()->addHour()->toIso8601String(), 'area' => [$ring], ...$overrides];
    }

    public function test_an_active_nl_alert_becomes_a_danger_area_that_reaches_its_edges(): void
    {
        Http::fake(['api.public-warning.app/*' => Http::response(['data' => [$this->nlAlert(), $this->nlAlert(['id' => 'old', 'stop_at' => now()->subMinute()->toIso8601String()])]])]);

        $this->artisan('veiligbuiten:ingest-nl-alert')->assertSuccessful();

        $incident = Incident::sole();
        $this->assertSame('nl_alert', $incident->source);
        $this->assertSame('brand', $incident->category);
        $this->assertSame('danger', DangerScale::level($incident));
        $this->assertSame('Polygon', $incident->geometry['type']);
        // Het gebied is ~1,1 × 1,4 km; het bereik moet minstens tot de hoeken gaan.
        $this->assertGreaterThanOrEqual(DangerScale::areaRadius($incident), DangerScale::alertRadius($incident));
        $this->assertGreaterThan(800, DangerScale::areaRadius($incident));
        Http::assertSent(fn ($request) => str_contains($request->header('User-Agent')[0] ?? '', 'VeiligerBuiten'));
    }

    public function test_an_unchanged_feed_is_not_downloaded_again_and_keeps_running_alerts(): void
    {
        Http::fakeSequence('api.public-warning.app/*')
            ->push(['data' => [$this->nlAlert()]], 200, ['ETag' => '"v1"'])
            ->push('', 304);

        $this->artisan('veiligbuiten:ingest-nl-alert')->assertSuccessful();
        $this->travel(30)->minutes();
        $this->artisan('veiligbuiten:ingest-nl-alert')->assertSuccessful();

        Http::assertSent(fn ($request) => ($request->header('If-None-Match')[0] ?? null) === '"v1"');
        $this->assertNull(Incident::sole()->resolved_at, 'a 304 must not look like the alert disappeared');
    }

    public function test_dutch_earthquakes_above_the_threshold_are_imported(): void
    {
        $body = implode("\n", [
            '#EventID|Time|Latitude|Longitude|Depth/km|Author|Catalog|Contributor|ContributorID|MagType|Magnitude|MagAuthor|EventLocationName|EventType',
            'knmi1|'.now()->subHour()->utc()->format('Y-m-d\TH:i:s').'|53.3|6.7|3.0|||KNMI|knmi1|MLn|2.4||Loppersum|induced or triggered event',
            'knmi2|'.now()->subHour()->utc()->format('Y-m-d\TH:i:s').'|50.69|7.17|12.0|||KNMI|knmi2|MLs|2.9||Bonn (Duitsland)|earthquake',
            'knmi3|'.now()->subHour()->utc()->format('Y-m-d\TH:i:s').'|53.2|6.6|3.0|||KNMI|knmi3|MLn|0.9||Garrelsweer|induced or triggered event',
        ]);
        Http::fake(['rdsa.knmi.nl/*' => Http::response($body)]);

        $this->artisan('veiligbuiten:ingest-knmi-quakes')->assertSuccessful();

        $quake = Incident::sole();
        $this->assertSame('Aardbeving', $quake->subtype);
        $this->assertSame('warn', DangerScale::level($quake));
        $this->assertStringContainsString('Loppersum', $quake->description);
    }

    public function test_p2000_recognises_threats_and_keeps_ambulance_rides_calm(): void
    {
        $entry = fn (string $id, string $title, string $category, string $point = '52.37 4.89') => "<entry><id>{$id}</id><title>{$title}</title><summary>{$title}</summary><updated>".now()->toIso8601String()."</updated><category term=\"{$category}\"/><georss:point>{$point}</georss:point></entry>";
        $feed = '<?xml version="1.0"?><feed xmlns="http://www.w3.org/2005/Atom" xmlns:georss="http://www.georss.org/georss">'
            .$entry('p1', 'P 1 Bommelding Damrak Amsterdam', 'politie')
            .$entry('p2', 'A1 Ambulance Kalverstraat Amsterdam', 'ambulance', '52.39 4.91')
            .$entry('p3', 'A2 Ambulance Damrak Amsterdam', 'ambulance')
            .'</feed>';
        Http::fake(['zwaailicht.nl/*' => Http::response($feed)]);

        $this->artisan('veiligbuiten:ingest-p2000')->assertSuccessful();

        $bomb = Incident::where('source_reference', 'p1')->sole();
        $this->assertSame(['onveilig', 'Bommelding', 5], [$bomb->category, $bomb->subtype, $bomb->severity]);
        $this->assertSame('info', DangerScale::level(Incident::where('source_reference', 'p2')->sole()));
        // p3 valt op dezelfde plek als de bommelding en wordt samengevoegd, zonder de ernst te verlagen.
        $this->assertSame(2, Incident::count());
        $this->assertSame(5, $bomb->fresh()->severity);
    }
}
