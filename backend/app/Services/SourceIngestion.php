<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\IncidentObservation;
use App\Models\SourceIngestionRun;
use App\Models\SourceRecord;
use Illuminate\Support\Collection;

/** Tracks every external import and only expires a missing feed item after two checks. */
class SourceIngestion
{
    public function start(string $source): SourceIngestionRun
    {
        return SourceIngestionRun::create(['source' => $source, 'status' => 'running', 'started_at' => now()]);
    }

    public function see(string $source, string $externalId, array $payload): void
    {
        SourceRecord::updateOrCreate(
            ['source' => $source, 'external_id' => $externalId],
            [
                'payload_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
                'payload' => $payload, 'received_at' => now(), 'processed_at' => now(),
                'last_seen_at' => now(), 'missing_since' => null, 'status' => 'processed',
            ],
        );
    }

    /** A feed is a snapshot: do not clear an item until it was absent for two source intervals. */
    public function reconcile(string $source, Collection $seenIds, int $staleAfterMinutes): int
    {
        $missing = SourceRecord::query()->where('source', $source)
            ->when($seenIds->isNotEmpty(), fn ($query) => $query->whereNotIn('external_id', $seenIds->all()))
            ->whereNull('missing_since')->get();

        foreach ($missing as $record) {
            $record->update(['missing_since' => now(), 'status' => 'missing']);
        }

        $confirmedMissing = SourceRecord::query()->where('source', $source)->whereNotNull('missing_since')
            ->where('missing_since', '<=', now()->subMinutes($staleAfterMinutes))->get();

        foreach ($confirmedMissing as $record) {
            $observation = IncidentObservation::query()->where('source', $source)->where('source_reference', $record->external_id)->first();
            if (! $observation) continue;
            $observation->update(['status' => 'missing']);
            $hasActiveOfficialObservation = IncidentObservation::query()
                ->where('incident_id', $observation->incident_id)
                ->where('source', '!=', $source)
                ->where('status', 'accepted')
                ->exists();
            if (! $hasActiveOfficialObservation) {
                Incident::query()->whereKey($observation->incident_id)->whereNull('resolved_at')
                    ->update(['status' => 'opgelost', 'resolved_at' => now(), 'expires_at' => now(), 'visibility' => 'archived', 'archived_at' => now()]);
            }
        }

        if ($confirmedMissing->isNotEmpty()) AreaSync::bump(); // bulk-update, buiten de model-events om

        return $confirmedMissing->count();
    }

    public function finish(SourceIngestionRun $run, int $seen, int $created, int $updated): void
    {
        $run->update(['status' => 'success', 'records_seen' => $seen, 'incidents_created' => $created, 'incidents_updated' => $updated, 'finished_at' => now()]);
    }

    public function fail(SourceIngestionRun $run, \Throwable $exception): void
    {
        $run->update(['status' => 'failed', 'error_message' => mb_substr($exception->getMessage(), 0, 4000), 'finished_at' => now()]);
    }
}
