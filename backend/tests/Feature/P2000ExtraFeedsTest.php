<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\IncidentObservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class P2000ExtraFeedsTest extends TestCase
{
    use RefreshDatabase;

    private function rss(string $items): string
    {
        return '<?xml version="1.0"?><rss version="2.0"><channel><title>t</title>'.$items.'</channel></rss>';
    }

    private function item(string $title, string $description, string $link, array $categories = [], ?string $guid = null): string
    {
        $cats = implode('', array_map(fn ($c) => "<category>{$c}</category>", $categories));

        return "<item><title>{$title}</title><link>{$link}</link><guid>".($guid ?? $link)."</guid><pubDate>".now()->toRfc7231String()."</pubDate><description>{$description}</description>{$cats}</item>";
    }

    /** PDOK nagebootst: Damrak in Amsterdam en een paar plaatsen. */
    private function fakePdok(): void
    {
        Http::fake(['api.pdok.nl/*' => function (Request $request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);
            $q = mb_strtolower($query['q']);
            $doc = match (true) {
                str_contains($q, 'damrak') => ['woonplaatsnaam' => 'Amsterdam', 'centroide_ll' => 'POINT(4.8952 52.3755)'],
                $query['fq'] === 'type:woonplaats' && in_array($q, ['amsterdam', 'utrecht', 'zoetermeer'], true) => ['woonplaatsnaam' => ucfirst($q), 'centroide_ll' => 'POINT(5.12 52.09)'],
                default => null,
            };

            return Http::response(['response' => ['docs' => $doc ? [$doc] : []]]);
        }]);
    }

    public function test_the_same_fire_from_several_feeds_becomes_one_better_confirmed_incident(): void
    {
        $this->fakePdok();
        Http::fake([
            'zwaailicht.nl/*' => Http::response('<?xml version="1.0"?><feed xmlns="http://www.w3.org/2005/Atom" xmlns:georss="http://www.georss.org/georss"><entry><id>z1</id><title>Woningbrand Damrak Amsterdam</title><summary>Brandweer met spoed naar Damrak in Amsterdam</summary><updated>'.now()->toIso8601String().'</updated><link rel="alternate" href="https://zwaailicht.nl/amsterdam/brand/z1"/><category term="brandweer"/><georss:point>52.3755 4.8952</georss:point></entry></feed>'),
            'sirenes.app/*' => Http::response($this->rss($this->item('Brandweer met spoed · Damrak, Amsterdam', 'Brandweer met spoed · Damrak, Amsterdam — P 1 BRT-01 Woningbrand Damrak Amsterdam', 'https://sirenes.app/incident/x1', ['brandweer', 'Amsterdam']))),
        ]);

        $this->artisan('veiligbuiten:ingest-p2000')->assertSuccessful();

        $fire = Incident::sole();
        $this->assertSame('brand', $fire->category);
        $this->assertSame('p2000_zwaailicht', $fire->source, 'the first feed names the incident');
        $this->assertSame('https://zwaailicht.nl/amsterdam/brand/z1', $fire->source_url);
        $this->assertSame(2, IncidentObservation::where('incident_id', $fire->id)->count(), 'both feeds are recorded as evidence');
    }

    public function test_ambulance_rides_never_reveal_a_street_or_house(): void
    {
        $this->fakePdok();
        Http::fake([
            'zwaailicht.nl/*' => Http::response('<?xml version="1.0"?><feed xmlns="http://www.w3.org/2005/Atom"></feed>'),
            'sirenes.app/*' => Http::response($this->rss($this->item('Ambulance met spoed · Damrak, Amsterdam', 'Ambulance met spoed · Damrak, Amsterdam — A1 Damrak 2726CV Amsterdam Rit 91306', 'https://sirenes.app/incident/a1', ['ambulance', 'Amsterdam']))),
        ]);

        $this->artisan('veiligbuiten:ingest-p2000')->assertSuccessful();

        $ride = Incident::sole();
        $this->assertSame('medisch', $ride->category);
        $this->assertSame('Ambulance-inzet', $ride->subtype);
        $this->assertStringNotContainsString('Damrak', $ride->description);
        $this->assertStringNotContainsString('2726', $ride->description);
        $this->assertNotEquals(52.3755, $ride->latitude, 'rounded to the neighbourhood, not the street');
        $this->assertSame('info', \App\Support\DangerScale::level($ride));
    }

    public function test_a_report_with_only_a_town_never_gets_its_own_pin(): void
    {
        $this->fakePdok();
        Http::fake([
            'zwaailicht.nl/*' => Http::response('<?xml version="1.0"?><feed xmlns="http://www.w3.org/2005/Atom"></feed>'),
            'sirenes.app/*' => Http::response($this->rss($this->item('Politie · Utrecht', 'Politie · Utrecht — P 1 Steekpartij Utrecht', 'https://sirenes.app/incident/u1', ['politie', 'Utrecht']))),
        ]);

        $this->artisan('veiligbuiten:ingest-p2000')->assertSuccessful();

        $this->assertSame(0, Incident::count(), 'a pin in the middle of Utrecht would be misleading');
    }

    public function test_one_broken_feed_does_not_stop_the_others(): void
    {
        $this->fakePdok();
        Http::fake([
            'zwaailicht.nl/*' => Http::response('', 500),
            'sirenes.app/*' => Http::response($this->rss($this->item('Brandweer met spoed · Damrak, Amsterdam', 'x — P 1 Woningbrand Damrak Amsterdam', 'https://sirenes.app/incident/x2', ['brandweer', 'Amsterdam']))),
        ]);

        $this->artisan('veiligbuiten:ingest-p2000');

        $this->assertSame(1, Incident::count());
    }

    public function test_raw_pager_texts_are_judged_sensibly(): void
    {
        $classifier = app(\App\Services\P2000FeedService::class);
        $this->assertSame(['category' => 'anders', 'subtype' => 'Brandweerinzet', 'severity' => 2], $classifier->classify('P 2 BRT-08 Liftopsluiting Isaac da Costastraat Hellevoetsluis', '', ['brandweer']));
        $this->assertSame('brand', $classifier->classify('P 2 BRT-02 BR berm/bosschage Rotterdamseweg Ridderkerk', '', ['brandweer'])['category'], '"BR" is how pagers write fire');
        $this->assertSame(2, $classifier->classify('4 Demonstratie Ahoyweg Rotterdam ICnum 628722', '', ['politie'])['severity']);
        $this->assertSame(3, $classifier->classify('1 Ongeval wegvervoer letsel Pascalweg Rotterdam', '', ['politie'])['severity']);
    }
}
