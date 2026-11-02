<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Services\ExternalIncidentIngestor;
use App\Services\ScrapePlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** De uitknoppen in veiligbuiten.source_switches werken voor elke bron zoals bij Zwaailicht. */
class SourceSwitchTest extends TestCase
{
    use RefreshDatabase;

    private function incident(string $source): Incident
    {
        return Incident::create([
            'category' => 'brand', 'subtype' => 'Brand', 'description' => 'Brand',
            'source' => $source, 'source_reference' => $source.'-1',
            'status' => 'officieel', 'visibility' => 'public', 'severity' => 4, 'confidence' => 1,
            'latitude' => 52.37, 'longitude' => 4.89, 'incident_type' => 'fire',
            'first_observed_at' => now(), 'last_activity_at' => now(), 'expires_at' => now()->addHours(3),
        ]);
    }

    public function test_disabled_sirenes_is_not_fetched_imported_or_shown(): void
    {
        config(['veiligbuiten.source_switches.p2000_sirenes' => false]);
        $old = $this->incident('p2000_sirenes');
        $kept = $this->incident('p2000_zwaailicht');
        Http::fake(['*' => Http::response('<rss version="2.0"><channel/></rss>')]);

        $this->artisan('veiligbuiten:ingest-p2000', ['--feed' => 'sirenes'])->assertSuccessful();
        $this->assertNull(app(ExternalIncidentIngestor::class)->p2000([
            'id' => 's-2', 'source_feed' => 'sirenes', 'category' => 'brand', 'subtype' => 'Brand', 'title' => 'Brand',
            'severity' => 4, 'latitude' => 52.4, 'longitude' => 4.9, 'updated_at' => now()->toIso8601String(),
        ]));

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'sirenes.app'));
        $this->assertSame('archived', $old->fresh()->visibility);
        $this->assertNull($kept->fresh()->archived_at);
        $this->assertSame(1, Incident::where('source', 'p2000_sirenes')->count());

        $status = collect($this->getJson('/api/v1/sources/status')->assertOk()->json('data'))->keyBy('source');
        $this->assertFalse($status['p2000_sirenes']['enabled']);
        $this->assertSame('disabled', $status['p2000_sirenes']['state']);
        $this->assertTrue($status['p2000']['enabled']);
    }

    public function test_disabled_nl_alert_is_skipped_by_planner_and_command(): void
    {
        config([
            'veiligbuiten.source_switches.nl_alert' => false,
            'veiligbuiten.scrape.sources' => ['nl_alert' => config('veiligbuiten.scrape.sources.nl_alert')],
        ]);
        $old = $this->incident('nl_alert');
        Http::fake();

        $this->assertSame([], app(ScrapePlanner::class)->run('fast', force: true));
        $this->artisan('veiligbuiten:ingest-nl-alert')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame('archived', $old->fresh()->visibility);
        $this->assertNull(app(ExternalIncidentIngestor::class)->nlAlert(['id' => 'x', 'category' => 'anders', 'description' => 'Test', 'latitude' => 52, 'longitude' => 5]));
        $this->assertFalse(collect($this->getJson('/api/v1/sources/status')->json('data'))->firstWhere('source', 'nl_alert')['enabled']);
    }

    public function test_open_data_sources_have_no_switch_and_stay_on(): void
    {
        $this->assertSame(['p2000_zwaailicht', 'p2000_sirenes', 'nl_alert'], array_keys(config('veiligbuiten.source_switches')));
        $this->assertTrue(\App\Support\SourceSwitch::enabled('politie'));
    }
}
