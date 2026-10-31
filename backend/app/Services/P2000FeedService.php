<?php

namespace App\Services;

use App\Services\Sources\Geocoder;
use App\Services\Sources\PoliteFetcher;
use App\Support\DangerKeywords;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class P2000FeedService
{
    public function __construct(private readonly PoliteFetcher $fetcher, private readonly Geocoder $geocoder) {}

    private const ZWAAILICHT_FEED = 'https://zwaailicht.nl/feed/meldingen.xml';

    /** P2000 discipline → Veiliger Buiten category. */
    private const DISCIPLINE_MAP = [
        'brandweer' => 'brand',
        'fire' => 'brand',
        'brand' => 'brand',
        'ambulance' => 'medisch',
        'medisch' => 'medisch',
        'politie' => 'onveilig',
        'police' => 'onveilig',
        'knrm' => 'onveilig',
        'lifeliner' => 'medisch',
        'trauma' => 'medisch',
    ];

    /** Keywords that indicate violence → maps to subtype. */
    private const VIOLENCE_INDICATORS = [
        'steekpartij' => 'Steekincident',
        'steekincident' => 'Steekincident',
        'steekwond' => 'Steekincident',
        'gestoken' => 'Steekincident',
        'schietpartij' => 'Schietincident',
        'schietincident' => 'Schietincident',
        'schoten' => 'Schietincident',
        'vuurwapen' => 'Schietincident',
        'geschoten' => 'Schietincident',
        'overval' => 'Overval of beroving',
        'beroving' => 'Overval of beroving',
        'straatroof' => 'Overval of beroving',
        'mishandeling' => 'Geweldsincident',
        'vechtpartij' => 'Geweldsincident',
        'geweld' => 'Geweldsincident',
        'bedreiging' => 'Bedreiging',
    ];

    /** Dreiging en gevaar voor omstanders; gaat voor brand en medisch. */
    private const THREAT_INDICATORS = [
        'bommelding' => ['onveilig', 'Bommelding', 5],
        'explosief' => ['onveilig', 'Verdacht object', 5],
        'verdacht pakket' => ['onveilig', 'Verdacht object', 4],
        'verdacht object' => ['onveilig', 'Verdacht object', 4],
        'explosie' => ['brand', 'Explosie', 5],
        'gaslek' => ['stoffen', 'Gaslucht / mogelijk gaslek', 4],
        'gaslucht' => ['stoffen', 'Gaslucht', 4],
        'gevaarlijke stof' => ['stoffen', 'Gevaarlijke stof', 4],
        'ongeval wegvervoer' => ['verkeer', 'Verkeersongeval', 3],
        'aanrijding' => ['verkeer', 'Verkeersongeval', 3],
        'verkeersongeval' => ['verkeer', 'Verkeersongeval', 3],
        // Weer en schade: goed om te weten, geen direct gevaar voor omstanders.
        'wateroverlast' => ['weer', 'Wateroverlast', 2],
        'stormschade' => ['weer', 'Storm / extreme wind', 2],
        'boom op weg' => ['blokkade', 'Boom op de weg', 2],
        'dier te water' => ['dier', 'Loslopend gevaarlijk dier', 2],
    ];

    /** Keywords that indicate fire type → maps to subtype. */
    private const FIRE_INDICATORS = [
        'automatische brandmelding' => 'Automatische brandmelding',
        'zeer grote brand' => 'Zeer grote brand',
        'grote brand' => 'Grote brand',
        'middelbrand' => 'Middelbrand',
        'woningbrand' => 'Woningbrand',
        'woning' => 'Woningbrand',
        'voertuigbrand' => 'Voertuigbrand',
        'autobrand' => 'Voertuigbrand',
        'containerbrand' => 'Containerbrand',
    ];

    /** Brandomvang → ernst. 'brandweer' en 'brandmelding' worden vóór het zoeken weggehaald. */
    private const SEVERITY_INDICATORS = [
        'grip' => 5,
        'zeer grote brand' => 5,
        'grote brand' => 5,
        'uitslaande brand' => 5,
        'middelbrand' => 4,
        'woningbrand' => 4,
        'brand' => 4,
    ];

    /** Prioriteitscodes als los woord (niet 'a1' in 'A10' of 'p1' in een ritnummer). */
    private const PRIORITY_SEVERITY = [
        '/\b(?:prio|p)\s?1\b/' => 4,
        '/\b(?:prio|p)\s?2\b/' => 3,
        '/\b(?:prio|p)\s?[3-5]\b/' => 2,
    ];

    /**
     * @return Collection|null null als de feed sinds de vorige keer niet is veranderd (304).
     */
    public function fetchZwaailicht(): ?Collection
    {
        if (! config('veiligbuiten.scrape.sources.p2000_zwaailicht.enabled', false)) {
            return collect();
        }

        try {
            $body = $this->fetcher->get(self::ZWAAILICHT_FEED, 30);

            return $body === null ? null : $this->parseAtomFeed($body);
        } catch (\Throwable $e) {
            Log::error('P2000: Zwaailicht error', ['error' => $e->getMessage()]);
            return collect();
        }
    }

    private function parseAtomFeed(string $xml): Collection
    {
        $feed = @simplexml_load_string($xml);
        if ($feed === false) {
            return collect();
        }

        $feed->registerXPathNamespace('georss', 'http://www.georss.org/georss');

        // Niet collect($feed->entry): dat maakt van alle entries één sleutel en houdt alleen de laatste over.
        $items = [];
        foreach ($feed->entry ?? [] as $entry) {
            if ($item = $this->parseEntry($entry)) {
                $items[] = $item;
            }
        }

        return collect($items);
    }

    private function parseEntry(\SimpleXMLElement $entry): ?array
    {
        $georss = $entry->children('http://www.georss.org/georss');
        [$lat, $lon] = $this->parseGeoPoint((string) ($georss->point ?? ''));

        if (!$lat || !$lon) {
            return null;
        }

        $categories = [];
        foreach ($entry->category ?? [] as $category) {
            $categories[] = strtolower((string) ($category['term'] ?? $category));
        }

        $title = $this->cleanTitle((string) $entry->title);
        $summary = (string) $entry->summary;
        // Een ambulancerit is privé: alleen de plaats, op buurtniveau. Zelfde regel als bij de andere P2000-bronnen.
        if ($this->isMedicalOnly($categories, $title.' '.$summary)) {
            $city = preg_match('/\bin ([^.]+?)\.?(?: Ingezet| Gemeld|$)/u', $summary, $m) ? trim($m[1]) : '';
            $title = $summary = trim('Ambulance'.($city !== '' ? " in {$city}" : ''));
            ['latitude' => $lat, 'longitude' => $lon] = $this->geocoder->roundForPrivacy(['latitude' => $lat, 'longitude' => $lon, 'precision_m' => 0]);
        }

        return [
            'id' => (string) $entry->id,
            'title' => $title,
            'description' => $summary ?: $title,
            'latitude' => $lat,
            'longitude' => $lon,
            ...$this->classify($title, $summary, $categories),
            'published_at' => (string) $entry->updated,
            'link' => (string) ($entry->link['href'] ?? ''),
            'source_feed' => 'zwaailicht',
            'raw_categories' => $categories,
        ];
    }

    /**
     * Categorie, soort en ernst uit een P2000-tekst. Gedeeld door alle P2000-feeds,
     * zodat dezelfde alarmering uit verschillende bronnen gelijk wordt ingedeeld.
     *
     * @param  list<string>  $disciplines  bijv. ['ambulance'], ['brandweer'], ['politie']
     * @return array{category: string, subtype: string, severity: int}
     */
    public function classify(string $title, string $summary, array $disciplines): array
    {
        $text = DangerKeywords::normalize($title.' '.$summary);

        return [
            'category' => $this->detectCategory($text, $disciplines),
            'subtype' => $this->detectSubtype($text, $disciplines),
            'severity' => $this->detectSeverity($text, $disciplines),
        ];
    }

    private function cleanTitle(string $title): string
    {
        return trim(preg_replace('/^[\x{1F300}-\x{1F9FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]+\s*/u', '', $title));
    }

    private function parseGeoPoint(string $point): array
    {
        if (empty($point)) {
            return [null, null];
        }

        $parts = preg_split('/\s+/', trim($point));
        return count($parts) >= 2 ? [(float) $parts[0], (float) $parts[1]] : [null, null];
    }

    private function detectCategory(string $text, array $categories): string
    {
        // Violence keywords override everything
        if ($this->matchesAny($text, array_keys(self::VIOLENCE_INDICATORS))) {
            return 'geweld';
        }
        if ($threat = $this->threat($text)) {
            return $threat[0];
        }

        // Brandweer zonder brand (liftopsluiting, assistentie, water) is geen brand.
        if ($this->isFireService($categories) && ! $this->mentionsFire($text)) {
            return 'anders';
        }

        // Check discipline from feed categories
        foreach ($categories as $cat) {
            if (isset(self::DISCIPLINE_MAP[$cat])) {
                return self::DISCIPLINE_MAP[$cat];
            }
        }

        return 'onveilig';
    }

    /** Alleen ambulance/traumaheli, zonder brandweer of politie en zonder geweld of dreiging. */
    private function isMedicalOnly(array $categories, string $text): bool
    {
        $text = DangerKeywords::normalize($text);

        return array_intersect($categories, ['ambulance', 'medisch', 'lifeliner', 'trauma'])
            && ! array_intersect($categories, ['brandweer', 'brand', 'fire', 'politie', 'police'])
            && ! $this->matchesAny($text, array_keys(self::VIOLENCE_INDICATORS)) && ! $this->threat($text);
    }

    private function isFireService(array $categories): bool
    {
        return (bool) array_intersect($categories, ['brandweer', 'brand', 'fire']);
    }

    /** Brand in de tekst; ruwe pagerteksten korten af tot 'BR' (bijv. "BR woning", "Grote BR"). */
    private function mentionsFire(string $text): bool
    {
        $text = str_replace(['brandweer', 'brandmelding'], '', $text);

        return (bool) preg_match('/brand|rook|vuur|\bbr\b|explosie|uitslaand/u', $text);
    }

    private function detectSubtype(string $text, array $categories): string
    {
        // Check violence indicators
        foreach (self::VIOLENCE_INDICATORS as $keyword => $subtype) {
            if (str_contains($text, $keyword)) {
                return $subtype;
            }
        }

        if ($threat = $this->threat($text)) {
            return $threat[1];
        }

        // Check fire indicators
        foreach (self::FIRE_INDICATORS as $keyword => $subtype) {
            if (str_contains($text, $keyword)) {
                return $subtype;
            }
        }

        // Default by discipline
        if (in_array('knrm', $categories)) {
            return 'Reddingsactie op het water';
        }
        if (in_array('lifeliner', $categories) || in_array('trauma', $categories)) {
            return 'Traumahelikopter ingezet';
        }
        if (in_array('ambulance', $categories) || in_array('medisch', $categories)) {
            return 'Ambulance-inzet';
        }
        if (in_array('politie', $categories)) {
            return 'Politie-inzet';
        }
        if (in_array('brandweer', $categories)) {
            return $this->mentionsFire($text) ? 'Brand' : 'Brandweerinzet';
        }

        return 'P2000 melding';
    }

    private function detectSeverity(string $text, array $categories = []): int
    {
        $calm = str_contains($text, 'zonder spoed') || str_contains($text, 'gepland vervoer');
        if ($threat = $this->threat($text)) {
            // Een gaslekkage 'zonder spoed' is meestal klein: één stap lager, maar nooit genegeerd.
            return $calm && $threat[2] >= 4 ? $threat[2] - 1 : $threat[2];
        }
        // Een automatische brandmelding is meestal loos alarm, en 'zonder spoed' is geen acuut gevaar:
        // wel tonen, niet alarmeren.
        if (str_contains($text, 'automatische brandmelding') || str_contains($text, 'zonder spoed')) {
            return 2;
        }
        // Alleen ambulance (zonder brandweer of politie) is een medische rit: goed om te weten, geen gevaar,
        // ook bij A1. De urgentie is medisch, niet voor omstanders.
        $medical = array_intersect($categories, ['ambulance', 'medisch', 'lifeliner', 'trauma']);
        if ($medical && ! array_intersect($categories, ['brandweer', 'brand', 'fire', 'politie', 'police'])
            && ! $this->matchesAny($text, array_keys(self::VIOLENCE_INDICATORS))) {
            return 2;
        }

        // Violence is always high severity
        if ($this->matchesAny($text, array_keys(self::VIOLENCE_INDICATORS))) {
            return 5;
        }
        // Brandweerinzet zonder brand of dreiging: goed om te weten.
        if ($this->isFireService($categories) && ! $this->mentionsFire($text)) {
            return 2;
        }
        // Ruwe politieteksten beginnen met de prioriteit als los cijfer ("1 Ongeval", "4 Demonstratie").
        if (array_intersect($categories, ['politie', 'police']) && preg_match('/^\s*([1-5])\s+\p{L}/u', $text, $priority)) {
            return (int) $priority[1] <= 2 ? 3 : 2;
        }

        $fireText = str_replace(['brandweer', 'brandmelding', 'brandgerucht'], '', $text);
        foreach (self::SEVERITY_INDICATORS as $keyword => $severity) {
            if (str_contains($fireText, $keyword)) {
                return $calm ? min(3, $severity) : $severity;
            }
        }
        foreach (self::PRIORITY_SEVERITY as $pattern => $severity) {
            if (preg_match($pattern, $text)) {
                return $severity;
            }
        }

        return $calm ? 2 : 3;
    }

    /** @return array{0: string, 1: string, 2: int}|null [categorie, subtype, ernst] */
    private function threat(string $text): ?array
    {
        foreach (self::THREAT_INDICATORS as $keyword => $match) {
            if (str_contains($text, $keyword)) {
                return $match;
            }
        }

        return null;
    }

    private function matchesAny(string $text, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }
        return false;
    }
}
