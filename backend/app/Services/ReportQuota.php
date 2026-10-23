<?php

namespace App\Services;

use App\Exceptions\ReportQuotaExceeded;
use App\Models\IncidentReport;
use App\Models\User;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Maximaal N meldingen per account per kalenderdag (Amsterdamse tijd).
 *
 * De telling komt uit de database (niet uit een cacheteller), en het plaatsen
 * gebeurt onder een lock per gebruiker. Twee gelijktijdige verzoeken kunnen het
 * limiet dus nooit samen overschrijden.
 */
class ReportQuota
{
    public function limit(): int
    {
        return config('veiligbuiten.reports_per_day');
    }

    public function used(User $user): int
    {
        return IncidentReport::query()
            ->where('user_id', $user->id)
            ->where('reported_at', '>=', $this->dayStart())
            ->count();
    }

    public function remaining(User $user): int
    {
        return max(0, $this->limit() - $this->used($user));
    }

    public function resetsAt(): Carbon
    {
        return $this->dayStart()->addDay();
    }

    /**
     * Voert $place uit als er nog ruimte is. $place moet de IncidentReport
     * aanmaken; het resultaat wordt teruggegeven.
     *
     * @template T
     * @param  Closure(): T  $place
     * @return T
     *
     * @throws ReportQuotaExceeded
     */
    public function consume(User $user, Closure $place): mixed
    {
        return Cache::lock("report-quota:{$user->id}", 10)->block(5, function () use ($user, $place) {
            return DB::transaction(function () use ($user, $place) {
                if ($this->remaining($user) === 0) {
                    throw new ReportQuotaExceeded($this->limit(), $this->resetsAt());
                }

                return $place();
            });
        });
    }

    public function summary(User $user): array
    {
        return ['limit' => $this->limit(), 'used' => $this->used($user), 'remaining' => $this->remaining($user), 'resets_at' => $this->resetsAt()->toIso8601String()];
    }

    private function dayStart(): Carbon
    {
        return now(config('veiligbuiten.timezone'))->startOfDay()->utc();
    }
}
