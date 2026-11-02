<?php

namespace App\Services\Sources;

use App\Services\P2000FeedService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Extra P2000-bron naast Zwaailicht: Sirenes.
 *
 * Die toont grotendeels dezelfde alarmeringen. Dat is de bedoeling: een melding die
 * in meerdere bronnen voorkomt wordt één melding die zekerder is, en valt één bron
 * weg, dan blijft P2000 werken. Alles gaat door dezelfde indeling als Zwaailicht.
 *
 * Locatie: Sirenes noemt straat en plaats. Die zetten we via PDOK
 * op straatniveau op de kaart, nooit op huisnummer. Een melding met alleen een plaatsnaam is te grof voor een eigen
 * stip; die telt alleen als bevestiging van een melding die er al is.
 */
class P2000ExtraFeeds
{
    private const MEDICAL = ['ambulance', 'lifeliner'];

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
                    default => null,
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

        return $this->item('sirenes', (string) ($entry->guid ?: $entry->link), $title, $raw, $discipline, $street, $city, (string) $entry->link, (string) $entry->pubDate);
    }

    private function item(string $feed, string $id, string $title, string $text, string $discipline, string $street, string $city, string $link, string $published): ?array
    {
        if ($id === '' || $city === '') {
            return null;
        }
        $time = rescue(fn () => Carbon::parse($published), now(), false);
        // Rollende feeds bevatten ook oude meldingen; die zijn geen actueel gevaar meer.
        if ($time->lt(now()->subHours(3))) {
            return null;
        }
        $point = $this->geocoder->street($street, $city);
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
        $safeText = $medical ? $this->withoutAddress($title, $city) : mb_substr($title, 0, 300);

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
