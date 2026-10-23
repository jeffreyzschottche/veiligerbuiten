<?php

namespace App\Console\Commands;

use App\Models\Incident;
use App\Models\ModerationEvent;
use Illuminate\Console\Command;

class ModerateIncident extends Command
{
    protected $signature = 'veiligbuiten:moderate-incident {incident : Incident-ID} {action : approve of reject} {--reason= : Interne reden}';
    protected $description = 'Publiceer of archiveer een melding die in review staat.';

    public function handle(): int
    {
        $incident = Incident::findOrFail((int) $this->argument('incident'));
        $action = $this->argument('action');
        if (! in_array($action, ['approve', 'reject'], true)) { $this->error('Actie moet approve of reject zijn.'); return self::INVALID; }
        if ($incident->visibility !== 'review') { $this->error('Alleen incidenten in review kunnen worden behandeld.'); return self::FAILURE; }
        $reason = (string) ($this->option('reason') ?: 'manual_moderation');
        if ($action === 'approve') {
            $incident->update(['visibility' => 'public', 'status' => 'gemeld', 'last_activity_at' => now()]);
            $moderationAction = 'approved';
        } else {
            $incident->update(['visibility' => 'archived', 'status' => 'afgewezen', 'resolved_at' => now(), 'archived_at' => now(), 'expires_at' => now()]);
            $moderationAction = 'rejected';
        }
        ModerationEvent::create(['incident_id' => $incident->id, 'action' => $moderationAction, 'reason' => $reason]);
        $this->info("Incident {$incident->id} is {$moderationAction}.");
        return self::SUCCESS;
    }
}
