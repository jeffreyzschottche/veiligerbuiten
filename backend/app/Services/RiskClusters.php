<?php

namespace App\Services;

use App\Models\Incident;
use App\Support\DangerScale;
use App\Support\GeoUtils;
use Illuminate\Support\Collection;

/**
 * Zoekt plekken waar op dit moment meerdere onafhankelijke, ernstige signalen
 * samenkomen: bijvoorbeeld een melding van iemand, een P2000-alarmering en een
 * politiebericht binnen een paar honderd meter. Zo'n cluster maakt elk signaal
 * zekerder (DangerScale verhoogt het niveau) en een losse gebruikersmelding met
 * een officieel signaal vlakbij geldt als bevestigd.
 *
 * Draait over alle actieve meldingen (landelijk een paar honderd) met een raster,
 * en schrijft alleen als er echt iets verandert: geen onnodige sync-updates.
 */
class RiskClusters
{
    /** Te alledaags om iets te versterken (een ambulancerit, drukte). */
    private const MIN_SEVERITY = 3;

    public function refresh(): int
    {
        $config = config('veiligbuiten.danger_scale');
        $radius = $config['cluster_radius_m'];
        $since = now()->subMinutes($config['cluster_window_minutes']);

        $active = Incident::query()
            ->where('visibility', 'public')->whereNull('resolved_at')->where('expires_at', '>', now())
            ->get();

        $signals = $active->filter(fn (Incident $i) => $i->severity >= self::MIN_SEVERITY && ($i->last_confirmed_at ?? $i->created_at) >= $since);
        $grid = $this->grid($signals, $radius);
        $changed = 0;

        $keys = ['cluster' => 1, 'official_nearby' => 1, 'level' => 1, 'reach' => 1];
        foreach ($active as $incident) {
            $neighbours = $this->neighbours($incident, $grid, $radius);
            $flags = array_filter([
                'cluster' => $neighbours->count(),
                'official_nearby' => $neighbours->contains(fn (Incident $n) => $n->source !== 'user'),
            ]);
            // Niveau en bereik veranderen ook door de tijd (versheid). Door ze hier vast te leggen
            // merkt de delta-sync dat, zonder dat telefoons elke keer alles opnieuw hoeven op te halen.
            $incident->risk_flags = array_diff_key((array) $incident->risk_flags, $keys) + $flags;
            $flags += ['level' => DangerScale::level($incident), 'reach' => (int) (round(DangerScale::alertRadius($incident) / 100) * 100)];
            $current = array_filter(array_intersect_key((array) $incident->getOriginal('risk_flags'), $keys));
            if ($flags == $current) {
                continue;
            }
            $merged = array_diff_key((array) $incident->risk_flags, $keys) + $flags;
            // last_activity_at: de alertwachtrij kijkt dan opnieuw (een cluster kan een escalatie zijn).
            Incident::query()->whereKey($incident->id)->update(['risk_flags' => $merged ? json_encode($merged) : null, 'last_activity_at' => now(), 'updated_at' => now()]);
            $changed++;
        }

        return $changed;
    }

    /** @return array<string, Collection<int, Incident>> */
    private function grid(Collection $incidents, int $radius): array
    {
        $grid = [];
        foreach ($incidents as $incident) {
            $grid[$this->cell($incident->latitude, $incident->longitude, $radius)][] = $incident;
        }

        return array_map(fn ($items) => collect($items), $grid);
    }

    private function cell(float $latitude, float $longitude, int $radius): string
    {
        $size = $radius / 111_000;

        return (int) floor($latitude / $size).':'.(int) floor($longitude / ($size * 1.6));
    }

    /** Andere signalen binnen de straal; meldingen van dezelfde bron tellen één keer. */
    private function neighbours(Incident $incident, array $grid, int $radius): Collection
    {
        [$row, $col] = array_map('intval', explode(':', $this->cell($incident->latitude, $incident->longitude, $radius)));
        $found = collect();
        for ($r = $row - 1; $r <= $row + 1; $r++) {
            for ($c = $col - 1; $c <= $col + 1; $c++) {
                $found = $found->merge($grid["{$r}:{$c}"] ?? []);
            }
        }

        return $found
            ->reject(fn (Incident $n) => $n->id === $incident->id)
            ->filter(fn (Incident $n) => GeoUtils::distanceMeters($incident->latitude, $incident->longitude, $n->latitude, $n->longitude) <= $radius)
            ->unique(fn (Incident $n) => $n->source === 'user' ? 'user:'.$n->id : $n->source)
            ->values();
    }
}
