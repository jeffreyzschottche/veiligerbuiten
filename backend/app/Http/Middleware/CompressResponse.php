<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gzip voor grote JSON-antwoorden (de sync). Een lijst van honderden meldingen
 * wordt zo ongeveer vijf keer kleiner. Doet niets als de webserver al comprimeert
 * of als de client geen gzip accepteert.
 */
class CompressResponse
{
    private const MIN_BYTES = 1024;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $content = $response->getContent();

        if (! str_contains((string) $request->header('Accept-Encoding'), 'gzip')
            || $response->headers->has('Content-Encoding')
            || ! str_contains((string) $response->headers->get('Content-Type'), 'json')
            || ! is_string($content) || strlen($content) < self::MIN_BYTES
            || ! function_exists('gzencode')) {
            return $response;
        }

        $response->setContent(gzencode($content, 5));
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Content-Length', (string) strlen($response->getContent()));
        if (! str_contains((string) $response->headers->get('Vary'), 'Accept-Encoding')) {
            $response->headers->set('Vary', trim($response->headers->get('Vary').', Accept-Encoding', ', '));
        }

        return $response;
    }
}
