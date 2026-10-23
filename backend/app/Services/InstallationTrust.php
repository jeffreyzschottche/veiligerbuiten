<?php

namespace App\Services;

use App\Models\Installation;
use App\Models\ModerationEvent;

class InstallationTrust
{
    public function adjust(Installation $installation, int $delta, string $reason, ?int $incidentId = null): void
    {
        $score = max(0, min(100, $installation->trust_score + $delta));
        $installation->update(['trust_score' => $score]);
        ModerationEvent::create(['installation_id' => $installation->id, 'incident_id' => $incidentId, 'action' => 'trust_adjusted', 'reason' => $reason, 'context' => ['delta' => $delta, 'score' => $score]]);
    }

    public function mayContribute(Installation $installation): bool
    {
        return ! $installation->blocked_at && $installation->trust_score >= 10;
    }
}
