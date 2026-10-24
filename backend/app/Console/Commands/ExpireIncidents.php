<?php

namespace App\Console\Commands;

use App\Services\IncidentLifecycle;
use App\Services\AreaSync;
use Illuminate\Console\Command;

class ExpireIncidents extends Command
{
    protected $signature = 'veiligbuiten:expire-incidents';
    protected $description = 'Archiveer verlopen meldingen en meldingen zonder recente activiteit.';

    public function handle(IncidentLifecycle $lifecycle): int
    {
        $expired = $lifecycle->sweep();
        if ($expired > 0) AreaSync::bump(); // bulk-updates lopen buiten de model-events om
        $this->info($expired.' meldingen verlopen.');

        return self::SUCCESS;
    }
}
