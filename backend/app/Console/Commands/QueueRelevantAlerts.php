<?php

namespace App\Console\Commands;

use App\Models\Incident;
use App\Services\AlertEngine;
use Illuminate\Console\Command;

class QueueRelevantAlerts extends Command
{
    protected $signature = 'veiligbuiten:queue-alerts';
    protected $description = 'Selecteer relevante, betrouwbare actuele incidenten voor pushaflevering.';

    public function handle(AlertEngine $alerts): int
    {
        $count = 0;
        foreach (Incident::query()->where('visibility', 'public')->where('expires_at', '>', now())
            ->whereIn('status', ['bevestigd', 'officieel'])->where('last_activity_at', '>', now()->subMinutes(10))
            ->cursor() as $incident) $count += $alerts->queueFor($incident);
        $this->info("{$count} alerts in de wachtrij geplaatst.");
        return self::SUCCESS;
    }
}
