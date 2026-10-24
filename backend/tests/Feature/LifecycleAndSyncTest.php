<?php

namespace Tests\Feature;

use App\Models\DangerHotspot;
use App\Models\Incident;
use App\Models\Installation;
use App\Models\SourceIngestionRun;
use App\Services\IncidentLifecycle;
use App\Support\DangerScale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LifecycleAndSyncTest extends TestCase
{
    use RefreshDatabase;

    private function incident(array $overrides = []): Incident
    {
        $createdAt = $overrides['created_at'] ?? null;
        unset($overrides['created_at']);
        $incident = Incident::create([
            'category' => 'geweld', 'subtype' => 'Vechtpartij', 'status' => 'gemeld', 'visibility' => 'public', 'source' => 'user',
            'severity' => 4, 'confidence' => .2, 'incident_type' => 'event', 'confirmations_count' => 2,
            'latitude' => 52.3702, 'longitude' => 4.8952, 'first_observed_at' => now(), 'last_confirmed_at' => now(), 'last_activity_at' => now(),
            'expires_at' => app(IncidentLifecycle::class)->initialExpiry($overrides['category'] ?? 'geweld'),
            ...$overrides,
        ]);
        if ($createdAt) {
            $incident->forceFill(['created_at' => $createdAt])->saveQuietly();
        }

        return $incident;
    }

    public function test_a_report_without_confirmations_expires_after_its_idle_time(): void
    {
        $fight = $this->incident();
        $roadblock = $this->incident(['category' => 'blokkade', 'subtype' => 'Weg geblokkeerd', 'severity' => 3, 'latitude' => 52.38]);

        $this->travel(95)->minutes();
        $this->artisan('veiligbuiten:expire-incidents')->assertSuccessful();

        $this->assertSame('verlopen', $fight->fresh()->status, 'a fight is over after 90 quiet minutes');
        $this->assertNull($roadblock->fresh()->archived_at, 'a closed road lasts longer');
    }

    public function test_confirmations_keep_a_report_alive_up_to_a_hard_maximum(): void
    {
        $this->actingAsSubscriber();
        $incident = $this->incident();
        $before = $incident->expires_at;

        $this->travel(80)->minutes();
        $installation = Installation::create(['token' => str_repeat('c', 64), 'trust_score' => 50]);
        $this->postJson("/api/v1/incidents/{$incident->id}/confirm", [], ['X-Installation-Token' => $installation->token])->assertOk();

        $this->assertTrue($incident->fresh()->expires_at->gt($before));
        $this->assertTrue($incident->fresh()->expires_at->lte($incident->first_observed_at->copy()->addMinutes(360)));
    }

    public function test_a_gone_vote_halves_the_remaining_time(): void
    {
        $this->actingAsSubscriber();
        $incident = $this->incident(['expires_at' => now()->addMinutes(80)]);
        $installation = Installation::create(['token' => str_repeat('g', 64), 'trust_score' => 50]);

        $this->postJson("/api/v1/incidents/{$incident->id}/resolve", [], ['X-Installation-Token' => $installation->token])->assertOk();

        $this->assertEqualsWithDelta(40, now()->diffInMinutes($incident->fresh()->expires_at), 1);
    }

    public function test_old_archives_and_logs_are_deleted(): void
    {
        $old = $this->incident(['archived_at' => now()->subDays(50), 'visibility' => 'archived', 'expires_at' => now()->subDays(50)]);
        $recent = $this->incident(['archived_at' => now()->subDay(), 'visibility' => 'archived', 'expires_at' => now()->subDay(), 'latitude' => 52.39]);
        SourceIngestionRun::create(['source' => 'p2000', 'status' => 'success', 'started_at' => now()->subDays(20)]);

        $this->artisan('veiligbuiten:prune-incidents')->assertSuccessful();

        $this->assertNull(Incident::find($old->id));
        $this->assertNotNull(Incident::find($recent->id));
        $this->assertSame(0, SourceIngestionRun::count());
    }

    public function test_an_unchanged_area_answers_304_and_a_cursor_returns_only_changes(): void
    {
        $this->actingAsSubscriber();
        $kept = $this->incident();
        $gone = $this->incident(['latitude' => 52.375, 'category' => 'brand', 'subtype' => 'Brand']);
        $this->travel(10)->seconds();

        $first = $this->getJson('/api/v1/incidents/sync?latitude=52.3712&longitude=4.8962')->assertOk()->assertJsonPath('mode', 'full')->assertJsonCount(2, 'data');
        // Het middelpunt valt op het raster: buren vragen hetzelfde gebied op.
        $first->assertJsonPath('center.latitude', 52.35)->assertJsonPath('center.longitude', 4.9);
        $etag = $first->headers->get('ETag');
        $cursor = $first->json('cursor');

        $this->getJson('/api/v1/incidents/sync?latitude=52.36&longitude=4.91', ['If-None-Match' => $etag])->assertStatus(304);

        $this->travel(3)->seconds();
        $gone->update(['status' => 'opgelost', 'resolved_at' => now(), 'visibility' => 'archived', 'archived_at' => now(), 'expires_at' => now()]);
        $new = $this->incident(['latitude' => 52.38, 'category' => 'drukte', 'subtype' => 'Grote menigte', 'severity' => 2]);

        $delta = $this->getJson("/api/v1/incidents/sync?latitude=52.3712&longitude=4.8962&since={$cursor}", ['If-None-Match' => $etag])
            ->assertOk()->assertJsonPath('mode', 'delta')->assertJsonPath('removed', [$gone->id]);
        $this->assertSame([$new->id], array_column($delta->json('changes'), 'id'));
        $this->assertNotContains($kept->id, array_column($delta->json('changes'), 'id'));
        $this->assertNotSame($etag, $delta->headers->get('ETag'));
    }

    public function test_a_change_in_the_same_second_as_the_last_sync_is_not_hidden_behind_a_304(): void
    {
        $this->actingAsSubscriber();
        $this->freezeTime();
        $incident = $this->incident();
        $etag = $this->getJson('/api/v1/incidents/sync?latitude=52.37&longitude=4.89')->headers->get('ETag');

        $incident->update(['description' => 'Verplaatst naar de overkant']); // zelfde seconde: max(updated_at) en aantal gelijk
        $this->travel(1)->seconds();

        $this->getJson('/api/v1/incidents/sync?latitude=52.37&longitude=4.89', ['If-None-Match' => $etag])->assertOk();
    }

    public function test_an_area_warning_from_outside_the_radius_still_reaches_people_inside_it(): void
    {
        $this->actingAsSubscriber();
        // Middelpunt ~40 km verderop, maar het gebied reikt tot hier.
        $this->incident(['source' => 'nl_alert', 'category' => 'brand', 'subtype' => 'NL-Alert', 'severity' => 5, 'latitude' => 52.73, 'longitude' => 4.8952,
            'geometry' => ['type' => 'Polygon', 'coordinates' => [[[4.6, 52.3], [5.2, 52.3], [5.2, 53.1], [4.6, 53.1], [4.6, 52.3]]]]]);

        $this->getJson('/api/v1/incidents/sync?latitude=52.37&longitude=4.89')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_the_sync_is_gzipped_when_the_phone_accepts_it(): void
    {
        $this->actingAsSubscriber();
        foreach (range(1, 12) as $i) $this->incident(['latitude' => 52.36 + $i / 1000]);

        $response = $this->get('/api/v1/incidents/sync?latitude=52.37&longitude=4.89', ['Accept-Encoding' => 'gzip', 'Accept' => 'application/json']);

        $response->assertOk()->assertHeader('Content-Encoding', 'gzip');
        $this->assertCount(12, json_decode(gzdecode($response->getContent()), true)['data']);
    }

    public function test_a_report_next_to_an_official_signal_counts_as_confirmed(): void
    {
        $report = $this->incident(['confirmations_count' => 1, 'severity' => 5]);
        $this->assertSame('warn', DangerScale::level($report));
        $this->incident(['source' => 'p2000_zwaailicht', 'status' => 'officieel', 'latitude' => 52.3712, 'severity' => 4]);

        $this->artisan('veiligbuiten:risk-clusters')->assertSuccessful();

        $this->assertTrue($report->fresh()->risk_flags['official_nearby']);
        $this->assertSame('danger', DangerScale::level($report->fresh()));
        $this->assertSame('danger', $report->fresh()->risk_flags['level']);
    }

    public function test_repeated_accidents_on_different_days_make_a_dangerous_spot(): void
    {
        $this->actingAsSubscriber();
        $accident = fn (int $daysAgo, float $offset = 0) => $this->incident([
            'category' => 'verkeer', 'subtype' => 'Verkeersongeval', 'source' => 'ndw', 'severity' => 4, 'latitude' => 52.3702 + $offset,
            'created_at' => now()->subDays($daysAgo), 'archived_at' => now()->subDays($daysAgo), 'visibility' => 'archived', 'expires_at' => now()->subDays($daysAgo),
        ]);
        $accident(2);
        $accident(9, 0.0004);
        $accident(15, -0.0003);
        // Drie op één avond elders: geen 'gevaarlijke plek'.
        foreach ([0.01, 0.0102, 0.0104] as $offset) $accident(3, $offset);

        $this->artisan('veiligbuiten:hotspots')->assertSuccessful();

        $spot = DangerHotspot::sole();
        $this->assertSame('verkeer', $spot->category);
        $this->assertSame(3, $spot->incidents_count);
        $this->getJson('/api/v1/incidents/sync?latitude=52.37&longitude=4.89')->assertOk()->assertJsonPath('hotspots.0.count', 3);
    }

    public function test_p2000_items_that_scroll_off_the_feed_stay_until_their_lifetime_ends(): void
    {
        $entry = fn (string $id, string $title, string $summary, string $category) => "<entry><id>{$id}</id><title>{$title}</title><summary>{$summary}</summary><updated>".now()->toIso8601String()."</updated><link rel=\"alternate\" href=\"https://zwaailicht.nl/x/{$id}\"/><category term=\"{$category}\"/><georss:point>52.37 4.89</georss:point></entry>";
        $feed = fn (string $entries) => '<?xml version="1.0"?><feed xmlns="http://www.w3.org/2005/Atom" xmlns:georss="http://www.georss.org/georss">'.$entries.'</feed>';
        Http::fakeSequence('zwaailicht.nl/*')
            ->push($feed($entry('s1', 'Steekpartij Damrak Amsterdam', 'Politie met spoed naar Damrak', 'politie')))
            ->push($feed($entry('b1', 'Brandweer melding', 'Brandweer uitgerukt.', 'brandweer').$entry('v1', 'Ambulance naar Handellaan in Voorschoten', 'Ambulance zonder spoed naar Handellaan in Voorschoten.', 'ambulance')));

        $this->artisan('veiligbuiten:ingest-p2000')->assertSuccessful();
        $this->travel(15)->minutes();
        $this->artisan('veiligbuiten:ingest-p2000')->assertSuccessful();

        $stabbing = Incident::where('source_reference', 's1')->sole();
        $this->assertNull($stabbing->resolved_at, 'scrolling off a rolling feed does not end a stabbing');
        $this->assertSame('https://zwaailicht.nl/x/s1', $stabbing->source_url);
        $this->assertNotSame('danger', DangerScale::level(Incident::where('source_reference', 'b1')->sole()), 'a bare "fire service alerted" is no danger');
        $ride = Incident::where('source_reference', 'v1')->sole();
        $this->assertNotSame('geweld', $ride->category, '"Voorschoten" contains "schoten" but is a town, not a shooting');
        $this->assertSame('info', DangerScale::level($ride));
    }

    public function test_police_news_keeps_ongoing_danger_and_skips_what_is_over(): void
    {
        config(['services.politie.enabled' => true, 'services.politie.nationwide' => true]);
        $message = fn (string $uid, string $title, string $intro, int $hoursAgo = 1) => [
            'uid' => $uid, 'titel' => $title, 'introductie' => $intro, 'url' => "https://www.politie.nl/nieuws/{$uid}.html",
            'publicatiedatum' => now('Europe/Amsterdam')->subHours($hoursAgo)->format('Y-m-d H:i:s'), 'locaties' => [['latitude' => 52.37, 'longitude' => 4.89]],
        ];
        Http::fake(['api.politie.nl/*' => Http::response(['iterator' => ['last' => true], 'nieuwsberichten' => [
            $message('a', 'Gewapende man voortvluchtig na schietpartij', 'De politie zoekt een man die op de vlucht is. Niet benaderen, bel 112.'),
            $message('b', 'Verdachte aangehouden na straatroof', 'Een 19-jarige man is aangehouden.'),
            $message('c', 'Voetbalseizoen: stabilisering van het aantal incidenten', 'Cijfers over het seizoen.'),
            $message('d', 'Overval op supermarkt', 'Getuigen gezocht.', 20),
        ]])]);

        $this->artisan('veiligbuiten:ingest-police-news')->assertSuccessful();

        $incident = Incident::sole();
        $this->assertSame('geweld', $incident->category);
        $this->assertSame('danger', DangerScale::level($incident));
        $this->assertSame('https://www.politie.nl/nieuws/a.html', $incident->source_url);
        Http::assertSent(fn ($request) => str_contains($request->header('User-Agent')[0] ?? '', 'VeiligBuiten'));
    }
}
