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
        // Elke nieuwe of gewijzigde situatie; bij meer bevestigingen groeit het bereik en komen er plekken bij.
        foreach (Incident::query()->where('visibility', 'public')->where('expires_at', '>', now())
            ->whereNull('resolved_at')->where('last_activity_at', '>', now()->subMinutes(10))
            ->cursor() as $incident) $count += $alerts->queueFor($incident);
        $this->info("{$count} alerts in de wachtrij geplaatst.");
        return self::SUCCESS;
    }
}
