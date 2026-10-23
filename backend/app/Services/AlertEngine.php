<?php

namespace App\Services;

use App\Jobs\SendAgentWebhook;
use App\Models\AlertDelivery;
use App\Models\Incident;
use App\Models\Place;
use App\Models\User;
use App\Support\DangerScale;
use App\Support\GeoUtils;
use App\Support\IncidentPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Bepaalt wie een pushbericht krijgt voor een incident.
 *
 * - Doelgroep: opgeslagen plekken van betalende gebruikers. De telefoon zelf
 *   controleert de actuele locatie lokaal; live locaties komen hier niet.
 * - Bereik: DangerScale::alertRadius groeit met het aantal bevestigingen, dus
 *   een melding die zekerder wordt bereikt vanzelf iets meer plekken.
 * - Geen spam: één bericht per gebruiker per incident per ernstniveau (alleen
 *   een escalatie geeft een nieuw bericht), een uurlimiet voor 'opletten',
 *   'goed om te weten' gaat stil in een bundel, en stille uren gelden voor
 *   alles behalve gevaar.
 */
class AlertEngine
{
    public function queueFor(Incident $incident): int
    {
        if (! $this->shouldAlert($incident)) {
            return 0;
        }

        $level = DangerScale::level($incident);
        $radius = DangerScale::alertRadius($incident);
        $queued = 0;

        foreach ($this->nearestPlacePerUser($incident, $radius) as ['place' => $place, 'distance' => $distance]) {
            $user = $place->user;
            if (! $this->wants($user, $incident, $level) || ! $this->isEscalation($user, $incident, $level)) {
                continue;
            }

            $queued += $this->deliver($user, $place, $incident, $level, $distance);
        }

        return $queued;
    }

    /**
     * Bundelt wachtende 'goed om te weten'-meldingen tot één stil bericht per
     * gebruiker, zodra de oudste lang genoeg wacht.
     */
    public function flushDigests(): int
    {
        $window = config('veiligbuiten.alerts.info_digest_minutes');
        $userIds = AlertDelivery::query()->where('status', 'digest')
            ->where('created_at', '<=', now()->subMinutes($window))
            ->distinct()->pluck('user_id');
        $sent = 0;

        foreach (User::whereIn('id', $userIds)->get() as $user) {
            $pending = AlertDelivery::query()->with(['incident', 'place'])->where('user_id', $user->id)->where('status', 'digest')->get();
            $pending->each->update(['status' => 'digested']);
            $live = $pending->filter(fn (AlertDelivery $d) => $d->incident && ! $d->incident->resolved_at && $d->incident->expires_at->isFuture());
            if ($live->isEmpty()) {
                continue;
            }

            $anchor = $live->sortByDesc(fn (AlertDelivery $d) => $d->incident->severity)->first();
            $payload = $this->withLocale($user, fn () => $this->digestPayload($live, $anchor));
            $sent += $this->queuePush($user, $anchor->place, $anchor->incident, 'info', 'digest:'.now()->timestamp, $payload);
        }

        return $sent;
    }

    private function shouldAlert(Incident $incident): bool
    {
        // Officiële bronnen meteen; gebruikersmeldingen zodra ze publiek zijn
        // (gevoelige categorieën pas na een onafhankelijke tweede melding).
        return $incident->visibility === 'public'
            && ! $incident->resolved_at
            && $incident->expires_at?->isFuture()
            && ($incident->source !== 'user' || $incident->confirmations_count >= 1);
    }

    /** @return Collection<int, array{place: Place, distance: float}> */
    private function nearestPlacePerUser(Incident $incident, int $radius): Collection
    {
        $reachKm = ($radius + config('veiligbuiten.places.max_radius_m')) / 1000;
        $latDelta = $reachKm / 111;
        $lonDelta = $reachKm / (111 * cos(deg2rad($incident->latitude)));

        return Place::query()
            ->with('user')
            ->where('alerts_enabled', true)
            ->whereBetween('latitude', [$incident->latitude - $latDelta, $incident->latitude + $latDelta])
            ->whereBetween('longitude', [$incident->longitude - $lonDelta, $incident->longitude + $lonDelta])
            ->whereHas('user', fn (Builder $query) => $this->subscribed($query))
            ->get()
            ->map(fn (Place $place) => ['place' => $place, 'distance' => GeoUtils::distanceMeters($place->latitude, $place->longitude, $incident->latitude, $incident->longitude)])
            ->filter(fn (array $match) => $match['distance'] <= $radius + $match['place']->radius_m)
            ->sortBy('distance')
            ->unique(fn (array $match) => $match['place']->user_id)
            ->values();
    }

    private function subscribed(Builder $query): Builder
    {
        return config('veiligbuiten.billing_bypass')
            ? $query
            : $query->where(fn (Builder $q) => $q->where('subscription_expires_at', '>', now())->orWhere('is_admin', true));
    }

    private function wants(User $user, Incident $incident, string $level): bool
    {
        $filters = $user->alertFilters();

        return in_array($incident->category, $filters['categories'], true)
            && DangerScale::rank($level) >= DangerScale::rank($filters['min_level']);
    }

    /** Alleen een eerste bericht, of een hoger niveau dan eerder voor dit incident. */
    private function isEscalation(User $user, Incident $incident, string $level): bool
    {
        $previous = AlertDelivery::query()->where('user_id', $user->id)->where('incident_id', $incident->id)->pluck('level');

        return $previous->isEmpty() || DangerScale::rank($level) > $previous->map(fn ($l) => DangerScale::rank($l))->max();
    }

    private function deliver(User $user, Place $place, Incident $incident, string $level, float $distance): int
    {
        $payload = $this->withLocale($user, fn () => $this->payload($incident, $place, $distance, $level));

        $toDigest = $level === 'info'
            || ($level === 'warn' && ($user->isQuietNow() || $this->warnsLastHour($user) >= config('veiligbuiten.alerts.warn_per_hour')));
        if ($toDigest) {
            AlertDelivery::create([
                'incident_id' => $incident->id, 'user_id' => $user->id, 'place_id' => $place->id,
                'event_key' => $level, 'level' => $level, 'status' => 'digest', 'reason' => 'bundled', 'payload' => $payload,
            ]);

            return 0;
        }

        if ($user->webhook_url) {
            SendAgentWebhook::dispatch($user->id, ['event' => 'alert', 'level' => $level, 'place' => $place->only(['id', 'name', 'label', 'latitude', 'longitude', 'radius_m']), 'incident' => $this->withLocale($user, fn () => IncidentPresenter::present($incident, $place->latitude, $place->longitude))]);
        }

        return $this->queuePush($user, $place, $incident, $level, $level, $payload);
    }

    private function queuePush(User $user, ?Place $place, Incident $incident, string $level, string $eventKey, array $payload): int
    {
        $installations = $user->installations()->whereNotNull('push_token')->whereNull('blocked_at')->get();

        // Zonder toestel bewaren we het toch, zodat dedupe en de AI-koppeling ('recente meldingen') kloppen.
        if ($installations->isEmpty()) {
            AlertDelivery::firstOrCreate(
                ['incident_id' => $incident->id, 'installation_id' => null, 'user_id' => $user->id, 'event_key' => $eventKey],
                ['place_id' => $place?->id, 'level' => $level, 'status' => 'no_device', 'payload' => $payload],
            );

            return 0;
        }

        $created = 0;
        foreach ($installations as $installation) {
            $delivery = AlertDelivery::firstOrCreate(
                ['incident_id' => $incident->id, 'installation_id' => $installation->id, 'event_key' => $eventKey],
                ['user_id' => $user->id, 'place_id' => $place?->id, 'level' => $level, 'status' => 'queued', 'reason' => 'place_'.$level, 'payload' => $payload],
            );
            $created += (int) $delivery->wasRecentlyCreated;
        }

        return $created;
    }

    private function warnsLastHour(User $user): int
    {
        return AlertDelivery::query()->where('user_id', $user->id)->where('level', 'warn')
            ->whereIn('status', ['queued', 'sent', 'simulated'])->where('created_at', '>', now()->subHour())
            ->distinct()->count('incident_id');
    }

    private function payload(Incident $incident, Place $place, float $distance, string $level): array
    {
        $subtype = IncidentPresenter::subtype($incident->subtype);
        $source = IncidentPresenter::SOURCE_LABELS[$incident->source] ?? null;
        $distanceText = IncidentPresenter::distanceText($distance);

        return [
            'title' => __('alerts.title', ['subtype' => $subtype, 'place' => $place->name]),
            'body' => $source
                ? __('alerts.body_official', ['distance' => $distanceText, 'place' => $place->name, 'source' => $source])
                : __('alerts.body_user', ['distance' => $distanceText, 'place' => $place->name, 'count' => $incident->confirmations_count]),
            'level' => $level,
            'data' => [
                'incident_id' => (string) $incident->id,
                'level' => $level,
                'latitude' => (string) $incident->latitude,
                'longitude' => (string) $incident->longitude,
            ],
        ];
    }

    private function digestPayload(Collection $deliveries, AlertDelivery $anchor): array
    {
        $titles = $deliveries->map(fn (AlertDelivery $d) => IncidentPresenter::subtype($d->incident->subtype))->unique()->values();
        $shown = $titles->take(2)->implode(', ');
        $placeName = $anchor->place?->name ?? '';

        return [
            'title' => __('alerts.digest_title', ['count' => $deliveries->count(), 'place' => $placeName]),
            'body' => $titles->count() > 2 ? __('alerts.digest_more', ['items' => $shown, 'count' => $titles->count() - 2]) : __('alerts.digest_body', ['items' => $shown]),
            'level' => 'info',
            'data' => ['incident_id' => (string) $anchor->incident_id, 'level' => 'info', 'digest' => '1'],
        ];
    }

    private function withLocale(User $user, callable $callback): mixed
    {
        $previous = app()->getLocale();
        app()->setLocale($user->locale ?? 'nl');
        try {
            return $callback();
        } finally {
            app()->setLocale($previous);
        }
    }
}
