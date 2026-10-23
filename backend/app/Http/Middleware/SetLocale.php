<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/** API-antwoorden in de taal van het account, anders die van de app (Accept-Language). */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = auth('sanctum')->user()?->locale ?? $request->getPreferredLanguage(['nl', 'en']) ?? 'nl';
        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
