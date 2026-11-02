<?php

namespace App\Services;

use App\Models\AlertDelivery;
use App\Models\DangerHotspot;
use App\Models\Incident;
use App\Models\SourceIngestionRun;
use App\Models\SourceRecord;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Hoe lang een melding leeft.
 *
 * Elke categorie heeft een eigen ritme (config veiligbuiten.lifecycle): een
 * vechtpartij is na anderhalf uur meestal voorbij, een afgesloten weg kan dagen
 * duren. Een melding leeft door zolang er interactie is:
 *
 *  - een bevestiging (of een nieuw bericht van een bron over hetzelfde) zet de
 *    verloopt-tijd op 'nu + extend', nooit voorbij 'eerste waarneming + max';
 *  - een 'niet meer aanwezig'-stem halveert de resterende tijd;
 *  - zonder bevestiging gedurende 'idle' minuten verloopt een gebruikersmelding vanzelf;
 *  - heeft een ander de gebruikersmelding bevestigd, dan blijft hij tot 3 'niet meer'-stemmen
 *    of 12 uur zonder nieuwe bevestiging (confirmed_idle_minutes);
 *    officiële meldingen volgen hun bron en de vangnet-limiet.
 *
 * Verlopen meldingen worden eerst gearchiveerd (zodat telefoons ze bij een delta
 * als 'verwijderd' zien) en na de bewaartermijn echt verwijderd.
 */
class IncidentLifecycle
{
    public function rules(string $category): array
    {
        $all = config('veiligbuiten.lifecycle.categories');

        return $all[$category] ?? $all['anders'];
    }

    public function initialExpiry(string $category, ?CarbonInterface $from = null): Carbon
    {
        return Carbon::instance($from ?? now())->addMinutes($this->rules($category)['ttl']);
    }

    /** Hard maximum since the first observation; official feeds use their own safety net. */
    public function hardLimit(Incident $incident): Carbon
    {
        $first = Carbon::instance($incident->first_observed_at ?? $incident->created_at ?? now());
        if ($incident->source !== 'user') {
            return $first->addHours(config('veiligbuiten.lifecycle.official_max_hours'));
        }

        return $first->addMinutes($this->rules($incident->category)['max']);
    }

    /** Een gebruikersmelding waar een ander op reageerde; de melder zelf telt als 1 bevestiging. */
    public function isConfirmedUserReport(Incident $incident): bool
    {
        return $incident->source === 'user' && (int) $incident->confirmations_count >= 2;
    }

    /**
     * Bevestiging of nieuwe bronupdate: leeft weer 'extend' minuten vanaf nu.
     * Een bevestigde gebruikersmelding blijft 12 uur na de laatste bevestiging, zonder vaste bovengrens.
     */
    public function touch(Incident $incident): Incident
    {
        $candidate = $this->isConfirmedUserReport($incident)
            ? now()->addMinutes(config('veiligbuiten.lifecycle.confirmed_idle_minutes'))
            : now()->addMinutes($this->rules($incident->category)['extend'])->min($this->hardLimit($incident));
        $expires = $candidate->max($incident->expires_at ?? now());

        $incident->forceFill(['expires_at' => $expires, 'last_activity_at' => now()])->save();

        return $incident;
    }

    /** Iemand zag het niet meer: de helft van de resterende tijd, met een kleine ondergrens. */
    public function weaken(Incident $incident): Incident
    {
        $remaining = max(0, now()->diffInSeconds($incident->expires_at, false));
        $floor = config('veiligbuiten.lifecycle.min_remaining_after_resolve_vote') * 60;
        $incident->forceFill([
            'expires_at' => now()->addSeconds((int) max(min($floor, $remaining), $remaining / 2)),
            'last_activity_at' => now(),
        ])->save();

        return $incident;
    }

    /**
     * Archiveert wat verlopen is: voorbij expires_at, of (gebruikersmeldingen)
     * te lang zonder activiteit. Geeft het aantal gearchiveerde meldingen terug.
     */
    public function sweep(): int
    {
        $archive = ['status' => 'verlopen', 'visibility' => 'archived', 'resolved_at' => now(), 'archived_at' => now()];

        $count = $this->archiveDisabledSources();
        $count += Incident::query()->whereNull('archived_at')->where('expires_at', '<=', now())->update($archive);

        // Bevestigde gebruikersmeldingen: 12 uur zonder nieuwe bevestiging.
        $count += Incident::query()
            ->whereNull('archived_at')->where('source', 'user')->where('confirmations_count', '>=', 2)
            ->where('last_confirmed_at', '<=', now()->subMinutes(config('veiligbuiten.lifecycle.confirmed_idle_minutes')))
            ->update($archive + ['expires_at' => now()]);

        // Onbevestigde gebruikersmeldingen: het ritme per soort.
        foreach (config('veiligbuiten.lifecycle.categories') as $category => $rules) {
            $count += Incident::query()
                ->whereNull('archived_at')->where('source', 'user')->where('category', $category)->where('confirmations_count', '<', 2)
                // Alleen bevestigingen tellen als interactie; een 'weg'-stem houdt niets in leven.
                ->where(fn ($query) => $query
                    ->where('last_confirmed_at', '<=', now()->subMinutes($rules['idle']))
                    ->orWhere(fn ($q) => $q->whereNull('last_confirmed_at')->where('created_at', '<=', now()->subMinutes($rules['idle']))))
                ->update($archive + ['expires_at' => now()]);
        }

        return $count;
    }

    /** Uitgeschakelde bronnen ook uit bestaande telefoon-caches en pushwachtrijen halen. */
    public function archiveDisabledSources(): int
    {
        if (config('veiligbuiten.scrape.sources.p2000_zwaailicht.enabled', false)) {
            return 0;
        }

        $count = Incident::query()->where('source', 'p2000_zwaailicht')->whereNull('archived_at')->update([
            'status' => 'verlopen', 'visibility' => 'archived', 'resolved_at' => now(),
            'archived_at' => now(), 'expires_at' => now(),
        ]);

        AlertDelivery::query()->whereIn('status', ['queued', 'digest', 'waiting_provider'])
            ->whereHas('incident', fn ($query) => $query->where('source', 'p2000_zwaailicht'))
            ->update(['status' => 'cancelled', 'reason' => 'source_disabled']);

        return $count;
    }

    /** Verwijdert oude archieven en logregels, zodat de database klein blijft. */
    public function purge(): array
    {
        $retention = config('veiligbuiten.retention');

        $incidents = 0;
        Incident::query()->whereNotNull('archived_at')
            ->where('archived_at', '<=', now()->subDays($retention['archived_incident_days']))
            ->select('id')->chunkById(500, function ($chunk) use (&$incidents) {
                // Observaties, stemmen, meldingen en afleveringen gaan mee via cascade.
                $incidents += Incident::query()->whereIn('id', $chunk->pluck('id'))->delete();
            });

        return [
            'incidents' => $incidents,
            'source_records' => SourceRecord::query()->whereNotNull('missing_since')
                ->where('missing_since', '<=', now()->subDays($retention['source_record_missing_days']))->delete()
                + SourceRecord::query()->where('received_at', '<=', now()->subDays($retention['archived_incident_days']))->delete(),
            'ingestion_runs' => SourceIngestionRun::query()->where('started_at', '<=', now()->subDays($retention['ingestion_run_days']))->delete(),
            'alert_deliveries' => AlertDelivery::query()->where('created_at', '<=', now()->subDays($retention['alert_delivery_days']))->delete(),
            'hotspots' => DangerHotspot::query()->where('computed_at', '<=', now()->subDays(2))->delete(),
        ];
    }
}
