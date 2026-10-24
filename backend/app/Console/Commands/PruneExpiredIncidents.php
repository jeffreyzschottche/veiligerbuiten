<?php

namespace App\Console\Commands;

use App\Services\IncidentLifecycle;
use App\Models\OpsEvent;
use App\Services\AreaSync;
use Illuminate\Console\Command;

class PruneExpiredIncidents extends Command
{
    protected $signature = 'veiligbuiten:prune-incidents';
    protected $description = 'Verwijder oude archieven, bronrecords en logregels na de bewaartermijn.';

    public function handle(IncidentLifecycle $lifecycle): int
    {
        $lifecycle->sweep();
        $removed = $lifecycle->purge();
        AreaSync::bump();
        $removed['ops_events'] = OpsEvent::query()->where('created_at', '<', now()->subDays(30))->delete();
        $this->info(collect($removed)->map(fn ($count, $what) => "{$what}: {$count}")->implode(', '));

        return self::SUCCESS;
    }
}
