<?php

namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * Laravels standaard-throttle telt per gebruiker of IP over álle routes samen: een paar
 * keer de kaart verversen kon dan betekenen dat melden (throttle:6) niet meer lukte, en
 * anonieme verzoeken (categorieën, installaties) aten de inlogpogingen op.
 * Hier krijgt elke route een eigen teller, per account of anders per IP.
 */
class ThrottlePerRoute extends ThrottleRequests
{
    protected function resolveRequestSignature($request)
    {
        $route = $request->route();
        if (! $route) {
            return parent::resolveRequestSignature($request);
        }

        // Ook op routes zonder auth-middleware (installaties, pushtoken) stuurt de app zijn
        // token mee; per account tellen houdt gebruikers achter één mobiel IP (CGNAT) uit elkaars weg.
        $user = $request->user() ?? auth('sanctum')->user();
        $who = $user ? 'user:'.$user->getAuthIdentifier() : 'ip:'.$request->ip();

        return sha1(implode('|', [$route->getDomain(), implode(',', $route->methods()), $route->uri(), $who]));
    }
}
