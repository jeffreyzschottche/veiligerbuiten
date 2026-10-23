<?php

namespace App\Console\Commands;

use App\Models\AirQualityStation;
use App\Models\Incident;
use App\Services\ExternalIncidentIngestor;
use App\Services\SourceIngestion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class IngestLuchtmeetnet extends Command
{
    protected $signature = 'veiligbuiten:ingest-luchtmeetnet {--station= : Alleen één Luchtmeetnet-stationnummer}';
    protected $description = 'Verwerk alleen officieel verhoogde Luchtmeetnet-metingen als contextsignaal.';

    public function handle(ExternalIncidentIngestor $ingestor, SourceIngestion $tracking): int
    {
        if (! config('services.luchtmeetnet.enabled')) {
            $this->warn('LUCHTMEETNET_ENABLED staat niet aan; import overgeslagen.');
            return self::SUCCESS;
        }

        $run = $tracking->start('luchtmeetnet');
        try {
            $base = rtrim(config('services.luchtmeetnet.url'), '/');
            $configuredStations = config('services.luchtmeetnet.stations', []);
            $stations = $this->option('station')
                ? AirQualityStation::where('station_number', $this->option('station'))->get()
                : (count($configuredStations)
                    ? AirQualityStation::whereIn('station_number', $configuredStations)->where('active', true)->get()
                    : AirQualityStation::where('active', true)->get());
            if ($stations->isEmpty()) throw new \RuntimeException('Geen Luchtmeetnet-stations gecatalogiseerd. Draai eerst veiligbuiten:sync-luchtmeetnet-stations.');
            $seen = collect(); $created = 0; $updated = 0;

            foreach ($stations as $station) {
                $number = (string) $station->station_number;
                if ($number === '') continue;
                $latitude = $station->latitude; $longitude = $station->longitude;
                if ($latitude === null || $longitude === null) continue;

                $measurements = Http::timeout(20)->get("{$base}/stations/{$number}/measurements", [
                    'order' => 'timestamp_measured', 'order_direction' => 'desc',
                ])->throw()->json('data', []);

                foreach (collect($measurements)->groupBy(fn ($row) => strtoupper((string) ($row['formula'] ?? '')))->map->first() as $formula => $measurement) {
                    $measuredAt = Carbon::parse($measurement['timestamp_measured'] ?? now());
                    if ($measuredAt->lt(now()->subMinutes(config('services.luchtmeetnet.max_measurement_age_minutes')))) continue;
                    $threshold = config("services.luchtmeetnet.thresholds.{$formula}");
                    $value = (float) ($measurement['value'] ?? -1);
                    if (! $threshold || $value < $threshold) continue;
                    $id = "{$number}:{$formula}";
                    $item = [
                        'id' => $id, 'latitude' => $latitude, 'longitude' => $longitude,
                        'subtype' => "Verhoogde {$formula}-waarde", 'severity' => $value >= ($threshold * 1.5) ? 3 : 2,
                        'description' => "Luchtmeetnet meet {$value} bij station ".($station->location ?? $number).'.',
                        'updated_at' => $measuredAt->toIso8601String(),
                    ];
                    $seen->push($id);
                    $exists = Incident::query()->where('source', 'luchtmeetnet')->where('source_reference', $id)->exists();
                    $ingestor->luchtmeetnet($item); $tracking->see('luchtmeetnet', $id, $item);
                    $exists ? $updated++ : $created++;
                }
                $station->update(['last_measured_at' => now()]);
                usleep((int) config('services.luchtmeetnet.request_spacing_ms') * 1000);
            }
            $tracking->reconcile('luchtmeetnet', $seen, 60);
            $tracking->finish($run, $seen->count(), $created, $updated);
            $this->info("{$seen->count()} verhoogde Luchtmeetnet-signalen verwerkt.");
            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $tracking->fail($run, $exception); report($exception);
            $this->error('Luchtmeetnet-import mislukt: '.$exception->getMessage());
            return self::FAILURE;
        }
    }

}
