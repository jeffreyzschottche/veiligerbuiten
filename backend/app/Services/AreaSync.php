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
use Illuminate\Support\Sleep;

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
 *  5. De cursor is het tijdstip van de staat zelf, niet 'nu'. Telefoons die dezelfde
 *     staat hadden, vragen dus dezelfde delta op, en die wordt gedeeld.
 *  6. ETag en cursor worden per dataversie bewaard: een ping zonder veranderingen
 *     kost geen enkele query. Elke wijziging aan een melding verhoogt de versie.
 *  7. Na elke ophaalronde waarin iets veranderde, zet de server voor de gebieden waar
 *     telefoons recent om vroegen de volledige lijst en de delta alvast klaar (warm).
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

    /** Hoogt de dataversie op; bewaarde ETags gelden dan niet meer. */
    public static function bump(): void
    {
        Cache::increment('vb:data-version');
    }

    public function etag(float $latitude, float $longitude, int $radiusKm, string $locale): string
    {
        return $this->stamp($latitude, $longitude, $radiusKm, $locale)['etag'];
    }

    /**
     * ETag en cursor van de huidige staat van een gebied. Per dataversie bewaard (en
     * hoogstens een minuut, als vangnet voor wijzigingen buiten de modellen om).
     *
     * @return array{etag: string, cursor: int}
     */
    public function stamp(float $latitude, float $longitude, int $radiusKm, string $locale): array
    {
        $key = 'vb:stamp:'.(int) Cache::get('vb:data-version', 0).":{$latitude}:{$longitude}:{$radiusKm}:{$locale}";
        if ($stamp = Cache::get($key)) {
            return $stamp;
        }

        $state = $this->area($latitude, $longitude, $radiusKm)
            ->where(fn (Builder $q) => $q->whereNull('archived_at')->orWhere('archived_at', '>=', $this->deltaHorizon()))
            ->toBase()->selectRaw('max(updated_at) as version, count(*) as total')->first();
        $hotspots = DangerHotspot::query()->max('computed_at').'|'.NeighbourhoodRisk::query()->max('computed_at');
        // updated_at telt in hele seconden. Veranderde er in de laatste paar seconden iets, dan kan er in
        // dezelfde seconde nog iets bijkomen dat max(updated_at) niet verandert: dan geen 304 beloven.
        $settling = $state->version && Carbon::parse($state->version)->gte(now()->subSeconds(2)) ? now()->getTimestamp() : null;

        $stamp = [
            'etag' => 'W/"'.substr(sha1(implode('|', [$latitude, $longitude, $radiusKm, $locale, $state->version, $state->total, $hotspots, $settling, 'v3'])), 0, 24).'"',
            // De eerste seconde die de telefoon nog niet volledig heeft. Is de laatste seconde nog
            // 'onrustig', dan kan daar nog iets bijkomen: die seconde dan opnieuw meenemen.
            'cursor' => $state->version ? Carbon::parse($state->version)->getTimestamp() + ($settling ? 0 : 1) : now()->getTimestamp() - 2,
        ];
        if (! $settling) {
            Cache::put($key, $stamp, 60);
        }

        return $stamp;
    }

    /** De delta, gedeeld door alle telefoons met dezelfde staat (ETag) en dezelfde cursor. */
    public function sharedDelta(float $latitude, float $longitude, int $radiusKm, Carbon $since, string $etag): ?array
    {
        $delta = Cache::remember('vb:delta:'.sha1($etag.'|'.$since->getTimestamp()), config('veiligbuiten.sync.response_cache_seconds'),
            fn () => $this->delta($latitude, $longitude, $radiusKm, $since) ?? ['none' => true]);

        return isset($delta['none']) ? null : $delta;
    }

    /**
     * Onthoudt dat telefoons om dit gebied vragen (hoogstens eens per 5 minuten een schrijfactie),
     * zodat warm() weet welke antwoorden het klaar moet zetten.
     */
    public function remember(float $latitude, float $longitude, int $radiusKm, string $locale, int $cursor): void
    {
        $key = "{$latitude}:{$longitude}:{$radiusKm}:{$locale}";
        if (! Cache::add("vb:cell-seen:{$key}", 1, 300)) {
            return;
        }
        $cells = Cache::get('vb:sync:cells', []);
        $cells[$key] = ['latitude' => $latitude, 'longitude' => $longitude, 'radius' => $radiusKm, 'locale' => $locale, 'seen' => now()->getTimestamp(), 'cursor' => $cells[$key]['cursor'] ?? $cursor];
        $horizon = now()->subHours(config('veiligbuiten.sync.warm_hours'))->getTimestamp();
        Cache::put('vb:sync:cells', array_filter($cells, fn (array $cell) => $cell['seen'] >= $horizon), now()->addHours(config('veiligbuiten.sync.warm_hours')));
    }

    /**
     * Zet voor de recent gevraagde gebieden de volledige lijst en de delta vanaf de vorige
     * staat klaar. Telefoons die bij waren, krijgen bij hun volgende ping een kant-en-klaar antwoord.
     */
    public function warm(): int
    {
        $cells = collect(Cache::get('vb:sync:cells', []))->sortByDesc('seen')->take(config('veiligbuiten.sync.warm_max_cells'));
        if ($cells->isEmpty()) {
            return 0;
        }
        // Wat zojuist veranderde kan in dezelfde seconde nog gezelschap krijgen (zie stamp); even
        // wachten tot de staat vaststaat, anders valt er niets te bewaren.
        $latest = Incident::query()->max('updated_at');
        if ($latest && Carbon::parse($latest)->gte(now()->subSeconds(3))) {
            Sleep::for(3)->seconds();
        }
        $locale = app()->getLocale();
        $updated = Cache::get('vb:sync:cells', []);
        try {
            foreach ($cells as $key => $cell) {
                app()->setLocale($cell['locale']);
                $stamp = $this->stamp($cell['latitude'], $cell['longitude'], $cell['radius'], $cell['locale']);
                $this->full($cell['latitude'], $cell['longitude'], $cell['radius'], $stamp['etag']);
                if ($cell['cursor'] && $cell['cursor'] !== $stamp['cursor']) {
                    $this->sharedDelta($cell['latitude'], $cell['longitude'], $cell['radius'], Carbon::createFromTimestamp($cell['cursor']), $stamp['etag']);
                }
                if (isset($updated[$key])) $updated[$key]['cursor'] = $stamp['cursor'];
            }
        } finally {
            app()->setLocale($locale);
        }
        Cache::put('vb:sync:cells', $updated, now()->addHours(config('veiligbuiten.sync.warm_hours')));

        return $cells->count();
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
        // De cursor is de eerste seconde die de telefoon nog niet volledig heeft (zie stamp).
        $changed = $this->within($this->area($latitude, $longitude, $radiusKm)->where('updated_at', '>=', $since)->get(), $latitude, $longitude, $radiusKm);
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
