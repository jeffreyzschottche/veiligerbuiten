<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class P2000FeedService
{
    private const ZWAAILICHT_FEED = 'https://zwaailicht.nl/feed/meldingen.xml';

    /** P2000 discipline → Veilig Buiten category. */
    private const DISCIPLINE_MAP = [
        'brandweer' => 'brand',
        'fire' => 'brand',
        'brand' => 'brand',
        'ambulance' => 'onveilig',
        'medisch' => 'onveilig',
        'politie' => 'onveilig',
        'police' => 'onveilig',
        'knrm' => 'onveilig',
        'lifeliner' => 'onveilig',
        'trauma' => 'onveilig',
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

    /** Keywords that indicate fire type → maps to subtype. */
    private const FIRE_INDICATORS = [
        'zeer grote brand' => 'Zeer grote brand',
        'grote brand' => 'Grote brand',
        'middelbrand' => 'Middelbrand',
        'woningbrand' => 'Woningbrand',
        'woning' => 'Woningbrand',
        'voertuigbrand' => 'Voertuigbrand',
        'autobrand' => 'Voertuigbrand',
        'containerbrand' => 'Containerbrand',
    ];

    /** Priority keywords → severity level. */
    private const SEVERITY_INDICATORS = [
        'grip' => 5,
        'zeer grote brand' => 5,
        'grote brand' => 5,
        'prio1' => 5,
        'p1' => 5,
        'a1' => 5,
        'prio2' => 4,
        'p2' => 4,
        'a2' => 4,
        'middelbrand' => 4,
        'brand' => 4,
    ];

    public function fetchZwaailicht(): Collection
    {
        try {
            $response = Http::timeout(30)->get(self::ZWAAILICHT_FEED);

            if (!$response->successful()) {
                Log::warning('P2000: Zwaailicht feed failed', ['status' => $response->status()]);
                return collect();
            }

            return $this->parseAtomFeed($response->body());
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

        return collect($feed->entry ?? [])
            ->map(fn ($entry) => $this->parseEntry($entry))
            ->filter(fn ($item) => $item !== null);
    }

    private function parseEntry(\SimpleXMLElement $entry): ?array
    {
        $georss = $entry->children('http://www.georss.org/georss');
        [$lat, $lon] = $this->parseGeoPoint((string) ($georss->point ?? ''));

        if (!$lat || !$lon) {
            return null;
        }

        $categories = collect($entry->category ?? [])
            ->map(fn ($cat) => strtolower((string) ($cat['term'] ?? $cat)))
            ->all();

        $title = $this->cleanTitle((string) $entry->title);
        $summary = (string) $entry->summary;
        $fullText = strtolower($title . ' ' . $summary);

        return [
            'id' => (string) $entry->id,
            'title' => $title,
            'description' => $summary ?: $title,
            'latitude' => $lat,
            'longitude' => $lon,
            'category' => $this->detectCategory($fullText, $categories),
            'subtype' => $this->detectSubtype($fullText, $categories),
            'severity' => $this->detectSeverity($fullText),
            'published_at' => (string) $entry->updated,
            'link' => (string) ($entry->link['href'] ?? ''),
            'source_feed' => 'zwaailicht',
            'raw_categories' => $categories,
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

        // Check discipline from feed categories
        foreach ($categories as $cat) {
            if (isset(self::DISCIPLINE_MAP[$cat])) {
                return self::DISCIPLINE_MAP[$cat];
            }
        }

        return 'onveilig';
    }

    private function detectSubtype(string $text, array $categories): string
    {
        // Check violence indicators
        foreach (self::VIOLENCE_INDICATORS as $keyword => $subtype) {
            if (str_contains($text, $keyword)) {
                return $subtype;
            }
        }

        // Check fire indicators
        foreach (self::FIRE_INDICATORS as $keyword => $subtype) {
            if (str_contains($text, $keyword)) {
                return $subtype;
            }
        }

        // Default by discipline
        if (in_array('ambulance', $categories) || in_array('medisch', $categories)) {
            return 'Medische noodsituatie';
        }
        if (in_array('politie', $categories)) {
            return 'Politie-inzet';
        }
        if (in_array('brandweer', $categories)) {
            return 'Brand';
        }

        return 'P2000 melding';
    }

    private function detectSeverity(string $text): int
    {
        foreach (self::SEVERITY_INDICATORS as $keyword => $severity) {
            if (str_contains($text, $keyword)) {
                return $severity;
            }
        }

        // Violence is always high severity
        if ($this->matchesAny($text, array_keys(self::VIOLENCE_INDICATORS))) {
            return 5;
        }

        return 3;
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
