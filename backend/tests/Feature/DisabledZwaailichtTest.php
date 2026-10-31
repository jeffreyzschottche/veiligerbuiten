<?php

namespace Tests\Feature;

use App\Models\AlertDelivery;
use App\Models\Incident;
use App\Models\IncidentObservation;
use App\Services\ExternalIncidentIngestor;
use App\Services\P2000FeedService;
use App\Services\ScrapePlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DisabledZwaailichtTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['veiligbuiten.scrape.sources.p2000_zwaailicht.enabled' => false]);
    }

    private function incident(string $source = 'p2000_zwaailicht'): Incident
    {
        return Incident::create([
            'category' => 'brand', 'subtype' => 'Brand', 'description' => 'Oude bronbeschrijving',
            'source' => $source, 'source_reference' => $source.'-fire',
            'source_url' => $source === 'p2000_zwaailicht' ? 'https://zwaailicht.nl/fire' : null,
            'status' => 'officieel', 'visibility' => 'public', 'severity' => 4, 'confidence' => 1,
            'latitude' => 52.37, 'longitude' => 4.89, 'incident_type' => 'fire',
            'first_observed_at' => now(), 'last_activity_at' => now(), 'expires_at' => now()->addHours(3),
        ]);
    }

    private function item(): array
    {
        return [
            'id' => 'sirenes-fire', 'source_feed' => 'sirenes', 'category' => 'brand', 'subtype' => 'Brand',
            'title' => 'Brand in Amsterdam', 'description' => 'Beschrijving van Sirenes', 'severity' => 4,
            'latitude' => 52.37, 'longitude' => 4.89, 'link' => 'https://sirenes.app/incident/fire',
            'updated_at' => now()->toIso8601String(),
        ];
    }

    public function test_disabled_feed_cannot_be_fetched_or_imported_directly(): void
    {
        Http::fake();

        $this->assertTrue(app(P2000FeedService::class)->fetchZwaailicht()->isEmpty());
        $this->assertNull(app(ExternalIncidentIngestor::class)->p2000([...$this->item(), 'source_feed' => 'zwaailicht']));

        Http::assertNothingSent();
        $this->assertDatabaseCount('incidents', 0);
        $this->assertDatabaseCount('source_records', 0);
        $this->assertDatabaseCount('incident_observations', 0);
    }

    public function test_explicit_command_and_forced_scheduler_skip_disabled_feed(): void
    {
        Http::fake();
        $source = config('veiligbuiten.scrape.sources.p2000_zwaailicht');
        config(['veiligbuiten.scrape.sources' => ['p2000_zwaailicht' => $source]]);

        $this->artisan('veiligbuiten:ingest-p2000', ['--feed' => 'zwaailicht'])->assertSuccessful();
        $this->assertSame([], app(ScrapePlanner::class)->run('fast', force: true));

        Http::assertNothingSent();
        $this->assertDatabaseCount('source_ingestion_runs', 0);
    }

    public function test_all_feeds_command_keeps_other_sources_running_without_zwaailicht(): void
    {
        Http::fake(['*' => Http::response('<rss version="2.0"><channel/></rss>')]);

        $this->artisan('veiligbuiten:ingest-p2000')->assertSuccessful();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'zwaailicht.nl'));
        $this->assertDatabaseHas('source_ingestion_runs', ['source' => 'p2000_sirenes', 'status' => 'success']);
        $this->assertDatabaseHas('source_ingestion_runs', ['source' => 'p2000_alarmeringen', 'status' => 'success']);
        $this->assertDatabaseHas('source_ingestion_runs', ['source' => 'p2000_monitor', 'status' => 'success']);
    }

    public function test_missing_configuration_also_blocks_the_feed(): void
    {
        config(['veiligbuiten.scrape.sources' => []]);
        Http::fake();

        $this->artisan('veiligbuiten:ingest-p2000', ['--feed' => 'zwaailicht'])->assertSuccessful();
        $this->assertTrue(app(P2000FeedService::class)->fetchZwaailicht()->isEmpty());

        Http::assertNothingSent();
    }

    public function test_dry_run_does_not_archive_existing_incidents(): void
    {
        $incident = $this->incident();
        Http::fake();

        $this->artisan('veiligbuiten:ingest-p2000', ['--feed' => 'zwaailicht', '--dry-run' => true])->assertSuccessful();

        $this->assertNull($incident->fresh()->archived_at);
        Http::assertNothingSent();
    }

    public function test_sweep_archives_old_reports_and_cancels_pending_alerts_only_for_zwaailicht(): void
    {
        $old = $this->incident();
        $other = $this->incident('p2000_sirenes');
        foreach (['queued', 'digest', 'waiting_provider', 'sent'] as $status) {
            AlertDelivery::create(['incident_id' => $old->id, 'event_key' => $status, 'level' => 'warn', 'status' => $status]);
        }
        $kept = AlertDelivery::create(['incident_id' => $other->id, 'event_key' => 'warn', 'level' => 'warn', 'status' => 'queued']);

        $this->artisan('veiligbuiten:expire-incidents')->assertSuccessful();

        $this->assertSame('archived', $old->fresh()->visibility);
        $this->assertSame('verlopen', $old->fresh()->status);
        $this->assertNotNull($old->fresh()->archived_at);
        $this->assertTrue($old->fresh()->expires_at->lte(now()));
        $this->assertNull($other->fresh()->archived_at);
        $this->assertSame(3, AlertDelivery::where('status', 'cancelled')->where('reason', 'source_disabled')->count());
        $this->assertDatabaseHas('alert_deliveries', ['incident_id' => $old->id, 'status' => 'sent']);
        $this->assertSame('queued', $kept->fresh()->status);
        $this->actingAsSubscriber();
        $this->getJson("/api/v1/incidents/{$old->id}")->assertNotFound();
        $this->getJson('/api/v1/incidents?latitude=52.37&longitude=4.89')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $other->id);
    }

    public function test_migration_invalidates_cached_sync_and_removes_old_reports_from_phone_delta(): void
    {
        $this->actingAsSubscriber();
        config(['veiligbuiten.scrape.sources.p2000_zwaailicht.enabled' => true]);
        $old = $this->incident();
        $other = $this->incident('p2000_sirenes');
        $this->travel(10)->seconds();
        $url = '/api/v1/incidents/sync?latitude=52.37&longitude=4.89';
        $first = $this->getJson($url)->assertOk()->assertJsonCount(2, 'data');
        $etag = $first->headers->get('ETag');
        $cursor = $first->json('cursor');

        config(['veiligbuiten.scrape.sources.p2000_zwaailicht.enabled' => false]);
        $this->travel(3)->seconds();
        $migration = require database_path('migrations/2026_10_31_210000_archive_disabled_zwaailicht_incidents.php');
        $migration->up();
        $archivedAt = $old->fresh()->archived_at;
        $this->travel(3)->seconds();
        $migration->up(); // Herhaald uitvoeren verandert het archief niet.
        $this->assertTrue($old->fresh()->archived_at->eq($archivedAt));

        $this->getJson($url."&since={$cursor}", ['If-None-Match' => $etag])
            ->assertOk()->assertJsonPath('mode', 'delta')->assertJsonPath('removed', [$old->id])->assertJsonCount(0, 'changes');
        $this->getJson($url)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $other->id);
    }

    public function test_other_feed_does_not_reuse_disabled_canonical_source_even_before_sweep(): void
    {
        $old = $this->incident();

        $new = app(ExternalIncidentIngestor::class)->p2000($this->item());

        $this->assertNotSame($old->id, $new->id);
        $this->assertSame('p2000_sirenes', $new->source);
        $this->assertSame('https://sirenes.app/incident/fire', $new->source_url);
        $this->assertSame('Beschrijving van Sirenes', $new->description);
        $this->assertSame('Oude bronbeschrijving', $old->fresh()->description);
    }

    public function test_previously_merged_observation_moves_to_the_other_feeds_own_incident(): void
    {
        $old = $this->incident();
        IncidentObservation::create([
            'incident_id' => $old->id, 'source' => 'p2000_sirenes', 'source_reference' => 'sirenes-fire',
            'kind' => 'external_update', 'status' => 'accepted', 'category' => 'brand', 'subtype' => 'Brand', 'observed_at' => now(),
        ]);
        $this->artisan('veiligbuiten:expire-incidents')->assertSuccessful();

        $new = app(ExternalIncidentIngestor::class)->p2000($this->item());

        $this->assertNotSame($old->id, $new->id);
        $this->assertSame('p2000_sirenes', $new->source);
        $this->assertSame('archived', $old->fresh()->visibility);
        $this->assertDatabaseHas('incident_observations', ['source' => 'p2000_sirenes', 'source_reference' => 'sirenes-fire', 'incident_id' => $new->id]);
    }

    public function test_enabled_source_is_not_archived(): void
    {
        config(['veiligbuiten.scrape.sources.p2000_zwaailicht.enabled' => true]);
        $incident = $this->incident();
        $migration = require database_path('migrations/2026_10_31_210000_archive_disabled_zwaailicht_incidents.php');

        $migration->up();
        $this->artisan('veiligbuiten:expire-incidents')->assertSuccessful();

        $this->assertNull($incident->fresh()->archived_at);
        $this->assertSame('public', $incident->fresh()->visibility);
    }
}
