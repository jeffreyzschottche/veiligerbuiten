<?php

namespace App\Providers;

use App\Models\DangerHotspot;
use App\Models\Incident;
use App\Models\NeighbourhoodRisk;
use App\Services\AreaSync;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Login-tokens verlopen na een periode zonder gebruik (glijdend, zodat actieve gebruikers ingelogd blijven).
        Sanctum::authenticateAccessTokensUsing(fn (PersonalAccessToken $token, bool $isValid) => $isValid
            && ($token->last_used_at ?? $token->created_at)?->gt(now()->subDays(config('veiligbuiten.token_idle_days'))));

        // Elke wijziging aan een melding maakt de bewaarde sync-ETags ongeldig.
        foreach ([Incident::class, DangerHotspot::class, NeighbourhoodRisk::class] as $model) {
            $model::saved(fn () => AreaSync::bump());
            $model::deleted(fn () => AreaSync::bump());
        }
    }
}
