<?php

namespace App\Console\Commands;

use App\Services\NeighbourhoodCrime;
use Illuminate\Console\Command;

class ImportNeighbourhoodCrime extends Command
{
    protected $signature = 'veiligbuiten:neighbourhood-crime';
    protected $description = 'Haal de politiecijfers per buurt op (CBS) en bepaal buurten met opvallend veel geweld en straatroof.';

    public function handle(NeighbourhoodCrime $crime): int
    {
        $result = $crime->refresh();
        $this->info("{$result['count']} buurten ({$result['from']} t/m {$result['to']}).");

        return self::SUCCESS;
    }
}
