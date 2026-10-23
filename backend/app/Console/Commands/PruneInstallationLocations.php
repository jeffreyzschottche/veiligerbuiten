<?php

namespace App\Console\Commands;

use App\Models\Installation;
use Illuminate\Console\Command;

class PruneInstallationLocations extends Command
{
    protected $signature = 'veiligbuiten:prune-installation-locations';
    protected $description = 'Wis verouderde precieze installatielocaties zodra ze niet meer relevant zijn voor alerts.';

    public function handle(): int
    {
        $count = Installation::query()
            ->whereNotNull('last_location_at')
            ->where('last_location_at', '<=', now()->subDay())
            ->update([
                'last_latitude' => null,
                'last_longitude' => null,
                'last_location_accuracy_m' => null,
                'last_location_at' => null,
            ]);

        $this->info("{$count} verouderde installatielocaties gewist.");

        return self::SUCCESS;
    }
}
