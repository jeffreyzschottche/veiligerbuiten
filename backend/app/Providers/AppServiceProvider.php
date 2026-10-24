<?php

namespace App\Providers;

use App\Models\DangerHotspot;
use App\Models\Incident;
use App\Models\NeighbourhoodRisk;
use App\Services\AreaSync;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Elke wijziging aan een melding maakt de bewaarde sync-ETags ongeldig.
        foreach ([Incident::class, DangerHotspot::class, NeighbourhoodRisk::class] as $model) {
            $model::saved(fn () => AreaSync::bump());
            $model::deleted(fn () => AreaSync::bump());
        }
    }
}
