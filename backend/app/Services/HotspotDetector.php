<?php

namespace App\Services;

use App\Models\DangerHotspot;
use App\Models\Incident;
use App\Support\GeoUtils;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Vindt gevaarlijke plekken: kruispunten met herhaalde ongevallen, straten met
 * herhaald geweld. Alleen officiële of door meerdere mensen bevestigde meldingen
 * tellen, en ze moeten over meerdere dagen verspreid zijn. Eén drukke avond of
 * één persoon die vaak meldt maakt dus geen 'gevaarlijke plek'.
 *
 * Werkwijze: een raster van ~250 m; de drukste cellen eerst, samen met hun
 * buren, zodat een plek op een celgrens niet in tweeën valt.
 */
class HotspotDetector
{
    public function refresh(): int
    {
        $config = config('veiligbuiten.hotspots');
        $cell = $config['cell_m'];
        $incidents = $this->qualifying($config);

        $cells = $incidents->groupBy(fn (Incident $i) => $this->cell($i->latitude, $i->longitude, $cell))
            ->sortByDesc(fn (Collection $group) => $group->count());
        $used = [];
        $hotspots = [];

        foreach ($cells as $key => $group) {
            if (isset($used[$key])) continue;
            [$row, $col] = array_map('intval', explode(':', $key));
            $centre = [$group->avg('latitude'), $group->avg('longitude')];
            $members = collect();
            $memberCells = [];
            for ($r = $row - 1; $r <= $row + 1; $r++) {
                for ($c = $col - 1; $c <= $col + 1; $c++) {
                    if (isset($used["{$r}:{$c}"]) || ! $cells->has("{$r}:{$c}")) continue;
                    $near = $cells["{$r}:{$c}"]->filter(fn (Incident $i) => GeoUtils::distanceMeters($centre[0], $centre[1], $i->latitude, $i->longitude) <= $cell * 1.5);
                    if ($near->isNotEmpty()) {
                        $members = $members->merge($near);
                        $memberCells[] = "{$r}:{$c}";
                    }
                }
            }
            $days = $members->map(fn (Incident $i) => $i->created_at->timezone(config('veiligbuiten.timezone'))->toDateString())->unique()->count();
            if ($members->count() < $config['min_incidents'] || $days < $config['min_days']) continue;

            foreach ($memberCells as $memberCell) $used[$memberCell] = true;
            $latitude = $members->avg('latitude');
            $longitude = $members->avg('longitude');
            $spread = $members->map(fn (Incident $i) => GeoUtils::distanceMeters($latitude, $longitude, $i->latitude, $i->longitude))->sort()->values();
            $hotspots[] = [
                'key' => 'h'.$key,
                'category' => $members->countBy('category')->sortDesc()->keys()->first(),
                'latitude' => round($latitude, 7),
                'longitude' => round($longitude, 7),
                'radius_m' => (int) max(150, min(500, round($spread[(int) floor(($spread->count() - 1) * 0.9)] + 60))),
                'incidents_count' => $members->count(),
                'days_count' => min(255, $days),
                'score' => round($members->sum(fn (Incident $i) => $i->severity * 0.5 ** ($i->created_at->diffInDays(now()) / $config['half_life_days'])), 2),
                'last_incident_at' => $members->max('created_at'),
                'computed_at' => now(),
            ];
        }

        DB::transaction(function () use ($hotspots) {
            DangerHotspot::query()->delete();
            foreach (array_chunk($hotspots, 200) as $chunk) DangerHotspot::query()->insert($chunk);
        });

        return count($hotspots);
    }

    private function qualifying(array $config): Collection
    {
        return Incident::query()
            ->where('created_at', '>=', now()->subDays($config['window_days']))
            ->whereIn('category', $config['categories'])
            ->where('severity', '>=', 3)
            // Officieel, of door meerdere mensen bevestigd; weggestemde meldingen tellen nooit.
            ->where(fn (Builder $q) => $q->where('source', '!=', 'user')->orWhere('confirmations_count', '>=', 2))
            ->where(fn (Builder $q) => $q->where('source', '!=', 'user')->orWhere('status', '!=', 'opgelost'))
            // Bij verkeer alleen echte ongevallen, niet elke file of pijlwagen.
            ->where(fn (Builder $q) => $q->where('category', '!=', 'verkeer')->orWhere('subtype', 'like', '%ongeval%'))
            // 'Onveilig' is breed; alleen het ernstige deel (bommelding, verdacht object, bevestigde dreiging).
            ->where(fn (Builder $q) => $q->where('category', '!=', 'onveilig')->orWhere('severity', '>=', 4))
            ->get(['id', 'category', 'severity', 'latitude', 'longitude', 'created_at']);
    }

    private function cell(float $latitude, float $longitude, int $size): string
    {
        $lat = $size / 111_000;

        return (int) floor($latitude / $lat).':'.(int) floor($longitude / ($lat * 1.6));
    }
}
