<?php

namespace App\Console\Commands;

use App\Models\Incident;
use App\Services\ExternalIncidentIngestor;
use App\Services\SourceIngestion;
use App\Services\Sources\PoliteFetcher;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * NL-Alert via de publieke feed van public-warning.app. Elke waarschuwing heeft
 * een gebied (een of meer polygonen); dat wordt de geometrie op de kaart en
 * bepaalt tot waar mensen een melding krijgen.
 */
class IngestNlAlert extends Command
{
    protected $signature = 'veiligbuiten:ingest-nl-alert';

    protected $description = 'Haal actieve NL-Alerts op.';

    public function handle(PoliteFetcher $fetcher, ExternalIncidentIngestor $ingestor, SourceIngestion $tracking): int
    {
        $run = $tracking->start('nl_alert');
        try {
            $body = $fetcher->get(config('services.nl_alert.url'));
            if ($body === null) {
                $tracking->finish($run, 0, 0, 0);

                return self::SUCCESS;
            }

            $seen = collect();
            [$created, $updated] = [0, 0];
            foreach (json_decode($body, true, flags: JSON_THROW_ON_ERROR)['data'] ?? [] as $alert) {
                $item = $this->normalize($alert);
                if (! $item) {
                    continue;
                }
                $exists = Incident::where('source', 'nl_alert')->where('source_reference', $item['id'])->exists();
                $ingestor->nlAlert($item);
                $tracking->see('nl_alert', $item['id'], $item);
                $seen->push($item['id']);
                $exists ? $updated++ : $created++;
            }
            $tracking->reconcile('nl_alert', $seen, 10);
            $tracking->finish($run, $seen->count(), $created, $updated);
            $this->info("NL-Alert: {$seen->count()} actief ({$created} nieuw).");

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $tracking->fail($run, $exception);
            report($exception);
            $this->error('NL-Alert mislukt: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    /** Alleen lopende waarschuwingen met een bruikbaar gebied. */
    public function normalize(array $alert): ?array
    {
        if (($alert['type'] ?? 'alert') !== 'alert' || empty($alert['id']) || empty($alert['stop_at'])) {
            return null;
        }
        if (Carbon::parse($alert['stop_at'])->isPast()) {
            return null;
        }

        $polygons = collect((array) ($alert['area'] ?? []))
            ->map(fn ($ring) => collect(preg_split('/\s+/', trim((string) $ring)))
                ->map(fn ($pair) => array_map('floatval', explode(',', $pair)))
                ->filter(fn ($point) => count($point) === 2 && $point[0] >= 50 && $point[0] <= 54 && $point[1] >= 3 && $point[1] <= 8)
                ->values())
            ->filter(fn ($ring) => $ring->count() >= 3)
            ->values();
        if ($polygons->isEmpty()) {
            return null;
        }

        $points = $polygons->flatten(1);
        $largest = $polygons->sortByDesc(fn ($ring) => $ring->count())->first();
        $message = trim((string) ($alert['message'] ?? ''));

        return [
            'id' => (string) $alert['id'],
            'latitude' => round($points->avg(fn ($point) => $point[0]), 6),
            'longitude' => round($points->avg(fn ($point) => $point[1]), 6),
            // GeoJSON: [lengte, breedte].
            'geometry' => ['type' => 'Polygon', 'coordinates' => [$largest->map(fn ($point) => [$point[1], $point[0]])->all()]],
            'category' => $this->category($message),
            'description' => mb_substr($message, 0, 500),
            'updated_at' => $alert['start_at'] ?? now()->toIso8601String(),
            'ends_at' => $alert['stop_at'],
        ];
    }

    private function category(string $message): string
    {
        $text = mb_strtolower($message);

        return match (true) {
            str_contains($text, 'brand') || str_contains($text, 'rook') => 'brand',
            str_contains($text, 'gas') || str_contains($text, 'stof') || str_contains($text, 'asbest') => 'stoffen',
            str_contains($text, 'water') || str_contains($text, 'storm') || str_contains($text, 'onweer') || str_contains($text, 'overstroming') => 'weer',
            default => 'onveilig',
        };
    }
}
