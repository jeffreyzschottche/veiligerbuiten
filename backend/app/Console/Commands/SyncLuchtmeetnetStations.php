<?php

namespace App\Console\Commands;

use App\Models\AirQualityStation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncLuchtmeetnetStations extends Command
{
    protected $signature = 'veiligbuiten:sync-luchtmeetnet-stations {--limit=15 : Verwerk maximaal N nog niet gecatalogiseerde stations per run}';
    protected $description = 'Ververs de landelijke Luchtmeetnet-stationcatalogus; dit is geen kaartincident-import.';

    public function handle(): int
    {
        $base = rtrim(config('services.luchtmeetnet.url'), '/');
        try {
            $numbers = [];
            for ($page = 1; $page <= 10; $page++) {
                $response = Http::timeout(25)->get("{$base}/stations", ['page' => $page])->throw()->json();
                foreach ($response['data'] ?? [] as $station) if (! empty($station['number'])) $numbers[] = $station['number'];
                if ($page >= (int) data_get($response, 'pagination.last_page', 1)) break;
            }
            $existing = AirQualityStation::query()->pluck('station_number')->all();
            $numbers = array_values(array_diff(array_unique($numbers), $existing));
            if ($this->option('limit')) $numbers = array_slice($numbers, 0, (int) $this->option('limit'));
            $synced = 0;
            foreach (array_unique($numbers) as $number) {
                $detail = Http::timeout(25)->get("{$base}/stations/{$number}")->throw()->json('data', []);
                $coordinates = data_get($detail, 'geometry.coordinates', []);
                if (count($coordinates) < 2) continue;
                AirQualityStation::updateOrCreate(['station_number' => $number], [
                    'location' => $detail['location'] ?? $number, 'municipality' => $detail['municipality'] ?? null,
                    'organisation' => $detail['organisation'] ?? null, 'longitude' => $coordinates[0], 'latitude' => $coordinates[1],
                    'components' => $detail['components'] ?? [], 'active' => true, 'last_catalogued_at' => now(),
                ]);
                $synced++;
                // Public API fair-use: stay below the documented request ceiling.
                usleep((int) config('services.luchtmeetnet.request_spacing_ms') * 1000);
            }
            $this->info("{$synced} Luchtmeetnet-stations gecatalogiseerd. ".count($numbers).' nieuwe stations in deze batch.');
            return self::SUCCESS;
        } catch (\Throwable $exception) {
            report($exception); $this->error('Luchtmeetnet-catalogus mislukt: '.$exception->getMessage());
            return self::FAILURE;
        }
    }
}
