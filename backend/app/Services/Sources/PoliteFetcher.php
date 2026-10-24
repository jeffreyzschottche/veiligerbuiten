<?php

namespace App\Services\Sources;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Haalt externe feeds netjes op: herkenbare User-Agent met contactadres en
 * conditionele verzoeken (ETag / Last-Modified). Is er niets veranderd, dan
 * antwoordt de bron met 304 en krijgen wij null terug; dat kost de bron bijna
 * niets en ons ook niet.
 *
 * Vraagt een bron ons te stoppen (429 te veel verzoeken, 503 overbelast, 403 geweigerd),
 * dan laten we die host met rust: de tijd uit Retry-After, anders een vaste pauze.
 * Tot die tijd gaat er geen enkel verzoek meer naartoe. Zo worden we geen last voor een bron.
 */
class PoliteFetcher
{
    public const USER_AGENT = 'VeiligerBuiten/1.0 (+https://veiligerbuiten.nl; hallo@veiligerbuiten.nl)';

    /** @return string|null De body, of null als de bron niets nieuws heeft. */
    public function get(string $url, int $timeout = 20, array $headers = []): ?string
    {
        self::guard($url);
        $key = 'veiligbuiten:feed-validators:'.sha1($url);
        $validators = Cache::get($key, []);

        $response = Http::timeout($timeout)
            ->withHeaders(array_filter([
                'User-Agent' => self::USER_AGENT,
                'If-None-Match' => $validators['etag'] ?? null,
                'If-Modified-Since' => $validators['last_modified'] ?? null,
                ...$headers,
            ]))
            ->get($url);
        self::observe($url, $response);

        if ($response->status() === 304) {
            return null;
        }
        $response->throw();

        Cache::put($key, array_filter([
            'etag' => $response->header('ETag') ?: null,
            'last_modified' => $response->header('Last-Modified') ?: null,
        ]), now()->addDay());

        return $response->body();
    }

    /** Pauze per statuscode als de bron geen Retry-After meegeeft (seconden). */
    private const PAUSE = [429 => 900, 503 => 300, 403 => 3600];

    /** Gooit een fout als deze host ons gevraagd heeft even te stoppen. */
    public static function guard(string $url): void
    {
        $until = Cache::get(self::cooldownKey($url));
        if ($until && $until > now()->getTimestamp()) {
            throw new SourceCoolingDown(parse_url($url, PHP_URL_HOST).' vroeg om een pauze tot '.Carbon::createFromTimestamp($until)->toTimeString().'.');
        }
    }

    /** Onthoudt een verzoek om te stoppen; Retry-After telt, tussen 1 minuut en 6 uur. */
    public static function observe(string $url, Response $response): void
    {
        $pause = self::PAUSE[$response->status()] ?? null;
        if ($pause === null) {
            return;
        }
        $retryAfter = trim((string) $response->header('Retry-After'));
        if (ctype_digit($retryAfter)) {
            $pause = (int) $retryAfter;
        } elseif ($retryAfter !== '' && ($date = strtotime($retryAfter))) {
            $pause = $date - now()->getTimestamp();
        }
        $pause = max(60, min(6 * 3600, $pause));
        Cache::put(self::cooldownKey($url), now()->getTimestamp() + $pause, $pause);
    }

    public static function cooldownUntil(string $url): ?int
    {
        $until = Cache::get(self::cooldownKey($url));

        return $until && $until > now()->getTimestamp() ? (int) $until : null;
    }

    private static function cooldownKey(string $url): string
    {
        return 'vb:host-cooldown:'.mb_strtolower((string) parse_url($url, PHP_URL_HOST));
    }
}
