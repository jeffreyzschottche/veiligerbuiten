<?php

namespace App\Console\Commands;

use App\Services\IncidentLifecycle;
use Illuminate\Console\Command;

class PruneExpiredIncidents extends Command
{
    protected $signature = 'veiligbuiten:prune-incidents';
    protected $description = 'Verwijder oude archieven, bronrecords en logregels na de bewaartermijn.';

    public function handle(IncidentLifecycle $lifecycle): int
    {
        $lifecycle->sweep();
        $removed = $lifecycle->purge();
        $this->info(collect($removed)->map(fn ($count, $what) => "{$what}: {$count}")->implode(', '));

        return self::SUCCESS;
    }
}
