<?php

namespace App\Services;

use App\Models\DangerHotspot;
use App\Models\Incident;
use App\Models\NeighbourhoodRisk;
use App\Support\DangerScale;
use App\Support\GeoUtils;
use App\Support\IncidentPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * De sync tussen server en telefoon, zo zuinig mogelijk:
 *
 *  1. Het middelpunt valt op een raster van ~5 km. Iedereen in dezelfde buurt
 *     vraagt dus precies hetzelfde gebied op.
 *  2. De 'staat' van een gebied is één goedkope query (laatste wijziging + aantal).
 *     Daaruit volgt een ETag. Is die gelijk aan wat de telefoon al heeft: 304,
 *     zonder body en zonder verdere queries.
 *  3. Heeft de telefoon een cursor (since), dan sturen we alleen wat sindsdien
 *     veranderde plus de id's van wat verdween.
 *  4. Een volledig antwoord wordt per gebied, taal en staat gecachet: de tweede
 *     telefoon in dezelfde buurt kost bijna niets.
 */
class AreaSync
{
    /** Gebiedswaarschuwingen (NL-Alert) kunnen van ver buiten de straal tot hier reiken. */
    private function areaReachKm(): float
    {
        return config('veiligbuiten.danger_scale.max_area_radius_m') / 1000;
    }

    /** @return array{float, float} */
    public function snap(float $latitude, float $longitude): array
    {
        $step = config('veiligbuiten.sync.snap_degrees');

        return [round(round($latitude / $step) * $step, 4), round(round($longitude / $step) * $step, 4)];
    }

    public function etag(float $latitude, float $longitude, int $radiusKm, string $locale): string
    {
        $state = $this->area($latitude, $longitude, $radiusKm)
            ->where(fn (Builder $q) => $q->whereNull('archived_at')->orWhere('archived_at', '>=', $this->deltaHorizon()))
            ->toBase()->selectRaw('max(updated_at) as version, count(*) as total')->first();
        $hotspots = DangerHotspot::query()->max('computed_at').'|'.NeighbourhoodRisk::query()->max('computed_at');
        // updated_at telt in hele seconden. Veranderde er in de laatste paar seconden iets, dan kan er in
        // dezelfde seconde nog iets bijkomen dat max(updated_at) niet verandert: dan geen 304 beloven.
        $settling = $state->version && Carbon::parse($state->version)->gte(now()->subSeconds(2)) ? now()->getTimestamp() : null;

        return 'W/"'.substr(sha1(implode('|', [$latitude, $longitude, $radiusKm, $locale, $state->version, $state->total, $hotspots, $settling, 'v2'])), 0, 24).'"';
    }

    /** Alles wat nu actief is, per gebied + taal + staat gecachet. */
    public function full(float $latitude, float $longitude, int $radiusKm, string $etag): array
    {
        return Cache::remember('vb:sync:'.sha1($etag), config('veiligbuiten.sync.response_cache_seconds'), fn () => [
            'data' => $this->active($latitude, $longitude, $radiusKm)
                ->take(config('veiligbuiten.sync.max_items'))
                ->map(fn (Incident $incident) => IncidentPresenter::compact($incident))->values()->all(),
            'hotspots' => $this->hotspots($latitude, $longitude, $radiusKm),
        ]);
    }

    /**
     * Wat er sinds $since veranderde. Null als een delta niet zinvol is (te oud,
     * of zoveel wijzigingen dat een volledige lijst kleiner is).
     *
     * @return array{changes: array, removed: array<int>, hotspots: array}|null
     */
    public function delta(float $latitude, float $longitude, int $radiusKm, Carbon $since): ?array
    {
        if ($since->lt($this->deltaHorizon()) || $since->isFuture()) {
            return null;
        }
        // Twee seconden overlap tegen klokverschil tussen schrijven en lezen.
        $changed = $this->within($this->area($latitude, $longitude, $radiusKm)->where('updated_at', '>', $since->copy()->subSeconds(2))->get(), $latitude, $longitude, $radiusKm);
        if ($changed->count() > config('veiligbuiten.sync.max_items') / 2) {
            return null;
        }
        [$live, $gone] = $changed->partition(fn (Incident $incident) => $this->isLive($incident));

        return [
            'changes' => $live->sortByDesc(fn (Incident $i) => DangerScale::score($i))->map(fn (Incident $i) => IncidentPresenter::compact($i))->values()->all(),
            'removed' => $gone->pluck('id')->values()->all(),
            'hotspots' => $this->hotspots($latitude, $longitude, $radiusKm),
        ];
    }

    /** Actief binnen de straal (of met een gebied dat tot hier reikt), zwaarste en zekerste eerst. */
    public function active(float $latitude, float $longitude, int $radiusKm): Collection
    {
        $incidents = $this->area($latitude, $longitude, $radiusKm)
            ->where('visibility', 'public')->where('expires_at', '>', now())
            ->whereNull('resolved_at')->where('resolved_count', '<', 3)
            ->get();

        return $this->within($incidents, $latitude, $longitude, $radiusKm)
            ->sortByDesc(fn (Incident $incident) => [DangerScale::rank(DangerScale::level($incident)), DangerScale::score($incident)])
            ->values();
    }

    public function hotspots(float $latitude, float $longitude, int $radiusKm): array
    {
        [$latDelta, $lonDelta] = $this->deltas($latitude, $radiusKm);

        return DangerHotspot::query()
            ->whereBetween('latitude', [$latitude - $latDelta, $latitude + $latDelta])
            ->whereBetween('longitude', [$longitude - $lonDelta, $longitude + $lonDelta])
            ->orderByDesc('score')->limit(100)->get()
            ->filter(fn (DangerHotspot $h) => GeoUtils::distanceMeters($latitude, $longitude, $h->latitude, $h->longitude) <= $radiusKm * 1000)
            ->map->toSync()->values()
            // Buurtcijfers van de politie erachter: statistiek, dus na wat er echt gemeld is.
            ->concat($this->neighbourhoods($latitude, $longitude, $radiusKm))
            ->all();
    }

    private function neighbourhoods(float $latitude, float $longitude, int $radiusKm): array
    {
        [$latDelta, $lonDelta] = $this->deltas($latitude, $radiusKm);

        return NeighbourhoodRisk::query()
            ->whereBetween('latitude', [$latitude - $latDelta, $latitude + $latDelta])
            ->whereBetween('longitude', [$longitude - $lonDelta, $longitude + $lonDelta])
            ->orderByDesc('ratio')->limit(config('veiligbuiten.neighbourhood_stats.per_area') * 2)->get()
            ->filter(fn (NeighbourhoodRisk $n) => GeoUtils::distanceMeters($latitude, $longitude, $n->latitude, $n->longitude) <= $radiusKm * 1000)
            ->take(config('veiligbuiten.neighbourhood_stats.per_area'))
            ->map->toSync()->values()->all();
    }

    /**
     * Advies voor de volgende sync. Is er gevaar dichtbij, dan wat vaker; is het
     * rustig, dan het normale interval. Nooit onder de ondergrens (load shedding).
     */
    public function nextSyncAfter(array $items): int
    {
        $base = config('veiligbuiten.sync.interval_seconds');
        $busy = collect($items)->contains(fn (array $item) => ($item['level'] ?? null) === 'danger');

        return max(config('veiligbuiten.sync.min_interval_seconds'), $busy ? (int) ($base / 4) : $base);
    }

    private function isLive(Incident $incident): bool
    {
        return $incident->visibility === 'public' && ! $incident->resolved_at && $incident->resolved_count < 3 && $incident->expires_at?->isFuture();
    }

    private function deltaHorizon(): Carbon
    {
        return now()->subHours(config('veiligbuiten.sync.delta_window_hours'));
    }

    /** Ruwe rechthoek: punten binnen de straal, gebieden met een ruimere marge. */
    private function area(float $latitude, float $longitude, int $radiusKm): Builder
    {
        [$latDelta, $lonDelta] = $this->deltas($latitude, $radiusKm);
        [$areaLat, $areaLon] = $this->deltas($latitude, $radiusKm + $this->areaReachKm());

        return Incident::query()->where(fn (Builder $query) => $query
            ->where(fn (Builder $q) => $q
                ->whereBetween('latitude', [$latitude - $latDelta, $latitude + $latDelta])
                ->whereBetween('longitude', [$longitude - $lonDelta, $longitude + $lonDelta]))
            ->orWhere(fn (Builder $q) => $q->whereNotNull('geometry')
                ->whereBetween('latitude', [$latitude - $areaLat, $latitude + $areaLat])
                ->whereBetween('longitude', [$longitude - $areaLon, $longitude + $areaLon])));
    }

    /** Echte cirkel; een gebied telt mee als zijn rand binnen de straal komt. */
    private function within(Collection $incidents, float $latitude, float $longitude, int $radiusKm): Collection
    {
        return $incidents->filter(fn (Incident $incident) => GeoUtils::distanceMeters($latitude, $longitude, $incident->latitude, $incident->longitude)
            <= $radiusKm * 1000 + DangerScale::areaRadius($incident));
    }

    /** @return array{float, float} */
    private function deltas(float $latitude, float $radiusKm): array
    {
        return [$radiusKm / 111, $radiusKm / (111 * max(.1, cos(deg2rad($latitude))))];
    }
}
