<?php

namespace App\Services\Sources;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Haalt externe feeds netjes op: herkenbare User-Agent met contactadres en
 * conditionele verzoeken (ETag / Last-Modified). Is er niets veranderd, dan
 * antwoordt de bron met 304 en krijgen wij null terug; dat kost de bron bijna
 * niets en ons ook niet.
 */
class PoliteFetcher
{
    public const USER_AGENT = 'VeiligBuiten/1.0 (+https://veiligbuiten.nl; hallo@veiligbuiten.nl)';

    /** @return string|null De body, of null als de bron niets nieuws heeft. */
    public function get(string $url, int $timeout = 20, array $headers = []): ?string
    {
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
}
