<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Services\IncidentLifecycle;
use App\Services\ScrapePlanner;
use App\Services\Sources\PoliteFetcher;
use App\Services\Sources\SourceCoolingDown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class ScrapePlannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Een nepbron: 'new' voegt een melding toe, 'same' verandert niets, 'fail' mislukt.
        Artisan::command('test:source {mode}', function (string $mode) {
            if ($mode === 'new') ScrapePlannerTest::incident();
            return $mode === 'fail' ? 1 : 0;
        });
        config([
            'veiligbuiten.scrape.jitter' => 0,
            'veiligbuiten.scrape.sources' => ['bron' => ['lane' => 'fast', 'command' => 'test:source', 'args' => ['mode' => 'same'], 'min' => 2, 'max' => 8]],
        ]);
        $this->travelTo(now()->setTimezone('Europe/Amsterdam')->setTime(14, 0)); // overdag
        Sleep::fake(syncWithCarbon: true);
    }

    public static function incident(array $overrides = []): Incident
    {
        return Incident::create([
            'category' => 'geweld', 'subtype' => 'Vechtpartij', 'status' => 'gemeld', 'visibility' => 'public', 'source' => 'politie',
            'severity' => 4, 'confidence' => .8, 'incident_type' => 'event', 'confirmations_count' => 1,
            'latitude' => 52.3702 + mt_rand(0, 100) / 100000, 'longitude' => 4.8952, 'first_observed_at' => now(), 'last_confirmed_at' => now(), 'last_activity_at' => now(),
            'expires_at' => app(IncidentLifecycle::class)->initialExpiry('geweld'), ...$overrides,
        ]);
    }

    private function mode(string $mode): void
    {
        config(['veiligbuiten.scrape.sources.bron.args.mode' => $mode]);
    }

    private function interval(): float
    {
        return app(ScrapePlanner::class)->status()['bron']['interval'];
    }

    public function test_a_quiet_source_is_asked_less_often_and_a_busy_one_stays_fast(): void
    {
        $planner = app(ScrapePlanner::class);
        $planner->run('fast');
        $this->assertSame(3.0, $this->interval(), 'unchanged: 2 × 1.5');

        $this->assertSame([], $planner->run('fast'), 'not due yet: nothing is fetched');

        foreach ([4.5, 6.75, 8.0, 8.0] as $expected) {
            $this->travel(10)->minutes();
            $planner->run('fast');
            $this->assertSame($expected, $this->interval());
        }

        $this->mode('new');
        $this->travel(10)->minutes();
        $this->assertTrue($planner->run('fast')['bron']['changed']);
        $this->assertSame(2.0, $this->interval(), 'something changed: back to the fastest pace');
    }

    public function test_errors_back_off_and_the_night_is_calmer(): void
    {
        $planner = app(ScrapePlanner::class);
        $this->mode('fail');
        foreach ([4.0, 8.0, 16.0] as $expected) {
            $planner->run('fast', force: true);
            $this->assertSame($expected, $this->interval());
        }
        $this->assertSame(3, $planner->status()['bron']['failures']);

        $this->mode('new');
        $this->travelTo(now()->setTimezone('Europe/Amsterdam')->setTime(3, 0));
        $planner->run('fast', force: true);
        $this->assertSame(4.0, $this->interval(), 'at night at least min × 2, even when busy');
        $this->assertSame(0, $planner->status()['bron']['failures']);
    }

    public function test_a_source_that_asks_us_to_stop_is_left_alone_until_its_retry_after(): void
    {
        Http::fake(['zwaailicht.nl/*' => Http::sequence()->push('', 429, ['Retry-After' => '600'])->push('<feed/>')]);
        $fetcher = app(PoliteFetcher::class);

        try {
            $fetcher->get('https://zwaailicht.nl/feed/meldingen.xml');
        } catch (\Throwable) {
        }
        Http::assertSentCount(1);

        try {
            $fetcher->get('https://zwaailicht.nl/feed/meldingen.xml');
            $this->fail('expected a pause');
        } catch (SourceCoolingDown) {
        }
        Http::assertSentCount(1); // geen tweede verzoek

        $this->travel(11)->minutes();
        $this->assertSame('<feed/>', $fetcher->get('https://zwaailicht.nl/feed/meldingen.xml'));
    }

    public function test_after_a_change_the_answer_for_phones_is_ready_and_a_ping_costs_no_queries(): void
    {
        $this->actingAsSubscriber();
        self::incident();
        $this->travel(5)->seconds();
        $url = '/api/v1/incidents/sync?latitude=52.3712&longitude=4.8962';
        $first = $this->getJson($url)->assertOk();
        [$etag, $cursor] = [$first->headers->get('ETag'), $first->json('cursor')];

        // Niets veranderd: een lege 304 zonder één query.
        DB::enableQueryLog();
        $this->getJson($url, ['If-None-Match' => $etag])->assertStatus(304);
        $this->assertSame([], DB::getQueryLog());
        DB::disableQueryLog();

        // Een bron levert iets nieuws; de planner zet de delta voor dit gebied klaar.
        $this->mode('new');
        $this->travel(5)->minutes();
        $this->assertSame(1, app(ScrapePlanner::class)->run('fast')['_warmed']);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $delta = $this->getJson("{$url}&since={$cursor}", ['If-None-Match' => $etag])->assertOk()->assertJsonPath('mode', 'delta')->assertJsonCount(1, 'changes');
        $this->assertSame([], DB::getQueryLog(), 'the delta was prepared in advance');

        // Een tweede telefoon met dezelfde staat krijgt precies dezelfde delta en dezelfde nieuwe cursor.
        $other = $this->getJson("{$url}&since={$cursor}", ['If-None-Match' => $etag]);
        $this->assertSame($delta->json('changes'), $other->json('changes'));
        $this->assertSame($delta->json('cursor'), $other->json('cursor'));
    }
}
