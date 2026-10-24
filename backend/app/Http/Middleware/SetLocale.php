<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * API-antwoorden in de taal die de app meestuurt (Accept-Language), zodat
 * scherm en data altijd overeenkomen, ook vlak na een taalwissel. Zonder
 * die header (MCP-clients, webhooks) geldt de taal van het account.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $requested = $request->hasHeader('Accept-Language') ? $request->getPreferredLanguage(['nl', 'en']) : null;
        $locale = $requested ?? auth('sanctum')->user()?->locale ?? 'nl';
        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
