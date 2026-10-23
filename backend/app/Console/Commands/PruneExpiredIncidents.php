<?php

namespace App\Console\Commands;

use App\Models\Incident;
use Illuminate\Console\Command;

class PruneExpiredIncidents extends Command
{
    protected $signature = 'veiligbuiten:prune-incidents';
    protected $description = 'Verwijder incidenten zodra hun actuele levensduur is verlopen.';

    public function handle(): int
    {
        $count = Incident::query()
            ->where('expires_at', '<=', now())
            ->whereNull('archived_at')
            ->update(['status' => 'verlopen', 'visibility' => 'archived', 'archived_at' => now()]);

        $this->info("{$count} verlopen incidenten gearchiveerd.");

        return self::SUCCESS;
    }
}
