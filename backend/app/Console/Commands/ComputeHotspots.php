<?php

namespace App\Console\Commands;

use App\Services\HotspotDetector;
use Illuminate\Console\Command;

class ComputeHotspots extends Command
{
    protected $signature = 'veiligbuiten:hotspots';
    protected $description = 'Bereken gevaarlijke plekken uit de meldingen van de afgelopen weken.';

    public function handle(HotspotDetector $detector): int
    {
        $this->info($detector->refresh().' gevaarlijke plekken.');

        return self::SUCCESS;
    }
}
