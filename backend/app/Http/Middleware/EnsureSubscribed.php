<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscribed
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->hasActiveSubscription()) {
            return response()->json(['message' => 'Een actief abonnement is nodig.', 'code' => 'subscription_required'], 402);
        }

        return $next($request);
    }
}
