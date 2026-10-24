<?php

namespace App\Services\Sources;

use App\Services\P2000FeedService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Extra P2000-bronnen naast Zwaailicht: Sirenes, Alarmeringen.nl en de P2000-monitor
 * (regio's Rotterdam-Rijnmond en Zuid-Holland Zuid, Lifeliners, KNRM).
 *
 * Ze tonen grotendeels dezelfde alarmeringen. Dat is de bedoeling: een melding die
 * in meerdere bronnen voorkomt wordt één melding die zekerder is, en valt één bron
 * weg, dan blijft P2000 werken. Alles gaat door dezelfde indeling als Zwaailicht.
 *
 * Locatie: Sirenes en Alarmeringen noemen straat en plaats; de monitor geeft ruwe
 * pagertekst (soms met postcode). Die zetten we via PDOK op straatniveau op de kaart,
 * nooit op huisnummer. Een melding met alleen een plaatsnaam is te grof voor een eigen
 * stip; die telt alleen als bevestiging van een melding die er al is.
 */
class P2000ExtraFeeds
{
    private const MEDICAL = ['ambulance', 'lifeliner'];
    /** Woorden in pagerteksten die geen plaats of straat zijn. */
    private const NOISE = ['bon', 'rit', 'vtg', 'ambu', 'dia', 'ja', 'nee', 'ter', 'plaatse', 'met', 'spoed', 'zonder', 'naar', 'prio', 'grote', 'br', 'brt', 'icnum', 'hlv', 'nr', 'to'];

    public function __construct(private readonly PoliteFetcher $fetcher, private readonly Geocoder $geocoder, private readonly P2000FeedService $classifier) {}

    /** @return array<string, string|list<string>> bron => feed-URL('s) */
    public function feeds(): array
    {
        return config('services.p2000_feeds');
    }

    /** @return Collection<int, array>|null null als niets veranderde (304) */
    public function fetch(string $feed): ?Collection
    {
        $urls = (array) ($this->feeds()[$feed] ?? []);
        $items = collect();
        $changed = false;
        foreach ($urls as $url) {
            try {
                $body = $this->fetcher->get($url, 20);
            } catch (\Throwable $e) {
                Log::warning("P2000 {$feed}: ophalen mislukt", ['url' => $url, 'error' => $e->getMessage()]);
                continue;
            }
            if ($body === null) {
                continue;
            }
            $changed = true;
            $xml = @simplexml_load_string($body);
            if ($xml === false) {
                continue;
            }
            foreach ($xml->channel->item ?? [] as $entry) {
                $item = match ($feed) {
                    'sirenes' => $this->sirenes($entry),
                    'alarmeringen' => $this->alarmeringen($entry),
                    default => $this->monitor($entry, $url),
                };
                if ($item) {
                    $items->push($item);
                }
            }
        }

        // Dezelfde pagertekst komt per capcode meerdere keren voor; één keer is genoeg.
        return $changed ? $items->unique('id')->values() : null;
    }

    /** "Ambulance zo spoedig mogelijk · Voermanstraat, Gorinchem" + ruwe tekst in de omschrijving. */
    private function sirenes(\SimpleXMLElement $entry): ?array
    {
        $title = trim((string) $entry->title);
        $raw = trim(preg_replace('/^.*?—\s*/u', '', (string) $entry->description));
        $categories = array_map(fn ($c) => mb_strtolower(trim((string) $c)), iterator_to_array($entry->category ?? [], false));
        $discipline = $this->discipline($categories[0] ?? $title);
        $place = trim((string) preg_replace('/^.*·\s*/u', '', $title));
        [$street, $city] = str_contains($place, ',') ? array_map('trim', explode(',', $place, 2)) : ['', $place];

        return $this->item('sirenes', (string) ($entry->guid ?: $entry->link), $title, $raw, $discipline, $street, $city, null, (string) $entry->link, (string) $entry->pubDate);
    }

    /** Titel = ruwe pagertekst, omschrijving = "Ambulance met spoed naar Straat in Plaats". */
    private function alarmeringen(\SimpleXMLElement $entry): ?array
    {
        $title = trim((string) $entry->title);
        $summary = trim((string) $entry->description);
        $link = preg_replace('/\?.*$/', '', (string) $entry->link);
        $discipline = $this->discipline(strtok($summary, ' ') ?: $title);
        $street = '';
        $city = '';
        if (preg_match('/\bnaar (.+?) in (.+)$/iu', $summary, $m)) {
            [$street, $city] = [$m[1], $m[2]];
        } elseif (preg_match('/\bin (.+)$/iu', $summary, $m)) {
            $city = $m[1];
        } elseif (preg_match('#alarmeringen\.nl/[^/]+/[^/]+/([^/]+)/#', $link, $m)) {
            $city = str_replace('-', ' ', $m[1]);
        }

        return $this->item('alarmeringen', (string) ($entry->guid ?: $link), $summary ?: $title, $title, $discipline, $street, $city, null, $link, (string) $entry->pubDate);
    }

    /** Ruwe pagertekst; "Korps/Voertuig" in de omschrijving zegt welke dienst het is. */
    private function monitor(\SimpleXMLElement $entry, string $url): ?array
    {
        $title = trim((string) $entry->title);
        $summary = trim(preg_replace('/\s+/', ' ', (string) $entry->description));
        // Ambulance eerst herkennen aan de urgentiecode (A0-A2, B1-B2) of 'AMBU' in de tekst: privacy gaat voor.
        $discipline = match (true) {
            str_contains($url, 'life-liners') => 'lifeliner',
            str_contains($url, 'reddingsmaatschappij') => 'knrm',
            (bool) preg_match('/^\s*(A0|A1|A2|B1|B2)\b|\bambu/i', $title.' '.$summary) => 'ambulance',
            default => $this->discipline(preg_match('/Korps\/Voertuig:\s*(\S+)/', $summary, $m) ? $m[1] : $title),
        };
        preg_match('/\b(\d{4})\s?([A-Z]{2})\b/', $title, $postcode);
        [$street, $city] = $postcode ? ['', ''] : $this->guessPlace($title);
        $published = (string) $entry->pubDate;
        // Zelfde tekst binnen dezelfde minuut = dezelfde alarmering (andere capcode).
        $id = 'monitor:'.sha1(mb_strtolower($title).'|'.substr((string) strtotime($published), 0, -2));

        return $this->item('monitor', $id, $title, $summary, $discipline, $street, $city, $postcode ? $postcode[1].$postcode[2] : null, 'https://www.p2000zhz-rr.nl/', $published, raw: true);
    }

    private function item(string $feed, string $id, string $title, string $text, string $discipline, string $street, string $city, ?string $postcode, string $link, string $published, bool $raw = false): ?array
    {
        if ($id === '' || ($city === '' && ! $postcode)) {
            return null;
        }
        $time = rescue(fn () => Carbon::parse($published), now(), false);
        // Rollende feeds bevatten ook oude meldingen; die zijn geen actueel gevaar meer.
        if ($time->lt(now()->subHours(3))) {
            return null;
        }
        $point = $postcode ? ($this->geocoder->postcode($postcode) ?? null) : $this->geocoder->street($street, $city);
        if (! $point) {
            return null;
        }
        $medical = in_array($discipline, self::MEDICAL, true);
        if ($medical) {
            // Een ambulancerit is privé: nooit een exacte straat, alleen de buurt.
            $point = $this->geocoder->roundForPrivacy($point);
        }
        $classified = $this->classifier->classify($title, $text, [$discipline]);
        // De officiële plaatsnaam van PDOK leest beter dan wat we uit een pagertekst haalden.
        $city = (string) ($point['place'] ?? '') ?: $city;
        // Ruwe pagertekst (huisnummers, postcodes, codes) tonen we nooit; dan een nette eigen zin.
        $safeText = $medical ? $this->withoutAddress($title, $city)
            : ($raw ? $this->summary($discipline, $classified['subtype'], $city) : mb_substr($title, 0, 300));

        return [
            'id' => $id,
            // Ook in onze eigen database geen woonadressen bij medische ritten.
            'title' => $safeText,
            'description' => $safeText,
            'latitude' => $point['latitude'],
            'longitude' => $point['longitude'],
            'location_precision_m' => $point['precision_m'],
            // Alleen een plaatsnaam: te grof voor een eigen stip, wel goed als bevestiging.
            'corroborate_only' => $point['precision_m'] >= 1000,
            ...$classified,
            'updated_at' => $time->toIso8601String(),
            'link' => $link,
            'source_feed' => $feed,
            'raw_categories' => [$discipline],
        ];
    }

    private function discipline(string $hint): string
    {
        $hint = mb_strtolower($hint);

        return match (true) {
            str_contains($hint, 'lifeliner') || str_contains($hint, 'trauma') => 'lifeliner',
            str_contains($hint, 'ambu') => 'ambulance',
            str_contains($hint, 'brand') || str_contains($hint, 'brw') => 'brandweer',
            str_contains($hint, 'politie') || str_contains($hint, 'pol') => 'politie',
            str_contains($hint, 'knrm') || str_contains($hint, 'redding') => 'knrm',
            default => 'onbekend',
        };
    }

    /**
     * Ruwe pagertekst zonder postcode: zoek van achteren naar voren een plaatsnaam
     * (een of twee woorden), en neem de woorden ervoor als straat. Alleen woorden zonder
     * cijfers, en codes zoals 'bon' of 'rit' tellen niet mee.
     *
     * @return array{0: string, 1: string}
     */
    private function guessPlace(string $text): array
    {
        $words = array_values(array_filter(preg_split('/[\s\/,()]+/u', $text), fn ($w) => $w !== '' && ! preg_match('/\d/', $w) && ! in_array(mb_strtolower($w), self::NOISE, true) && mb_strlen($w) > 1));
        for ($i = count($words) - 1, $tries = 0; $i >= 0 && $tries < 4; $i--, $tries++) {
            foreach ([2, 1] as $size) {
                if ($i - $size + 1 < 0) {
                    continue;
                }
                $city = implode(' ', array_slice($words, $i - $size + 1, $size));
                if ($this->geocoder->city($city)) {
                    return [implode(' ', array_slice($words, max(0, $i - $size - 2), min(3, $i - $size + 1))), $city];
                }
            }
        }

        return ['', ''];
    }

    /** "Brandweer · Brand in Ridderkerk": leesbaar, zonder adres of pagercodes. */
    private function summary(string $discipline, string $subtype, string $city): string
    {
        $service = ['brandweer' => 'Brandweer', 'politie' => 'Politie', 'knrm' => 'KNRM', 'lifeliner' => 'Traumahelikopter'][$discipline] ?? 'Hulpdiensten';
        $where = $city !== '' ? ' in '.$this->placeName($city) : '';

        return "{$service} · {$subtype}{$where}";
    }

    /** Voor ambulanceritten: geen straat of huisnummer in de tekst, alleen de dienst en de plaats. */
    private function withoutAddress(string $title, string $city): string
    {
        $kind = preg_match('/\b(A1|A2|B1|B2)\b/i', $title, $m) ? strtoupper($m[1]).' ' : '';

        return $city !== '' ? trim("Ambulance {$kind}in ".$this->placeName($city)) : trim("Ambulance {$kind}in de buurt");
    }

    /** PDOK levert al nette namen ('s-Hertogenbosch); alleen zelf geparste kleine letters krijgen hoofdletters. */
    private function placeName(string $city): string
    {
        return $city === mb_strtolower($city) ? mb_convert_case($city, MB_CASE_TITLE) : $city;
    }
}
