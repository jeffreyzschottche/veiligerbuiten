<?php

namespace App\Console\Commands;

use App\Services\IncidentLifecycle;
use Illuminate\Console\Command;

class ExpireIncidents extends Command
{
    protected $signature = 'veiligbuiten:expire-incidents';
    protected $description = 'Archiveer verlopen meldingen en meldingen zonder recente activiteit.';

    public function handle(IncidentLifecycle $lifecycle): int
    {
        $this->info($lifecycle->sweep().' meldingen verlopen.');

        return self::SUCCESS;
    }
}
