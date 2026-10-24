<?php

namespace App\Console\Commands;

use App\Models\Incident;
use App\Services\ExternalIncidentIngestor;
use App\Services\SourceIngestion;
use App\Services\Sources\PoliteFetcher;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Aardbevingen van het KNMI (FDSN-eventservice). We tonen alleen bevingen in
 * Nederland van de afgelopen zes uur vanaf magnitude 1,5; kleinere zijn niet
 * voelbaar en zouden de kaart alleen vullen.
 */
class IngestKnmiQuakes extends Command
{
    protected $signature = 'veiligbuiten:ingest-knmi-quakes';

    protected $description = 'Haal recente aardbevingen in Nederland op bij het KNMI.';

    private const MIN_MAGNITUDE = 1.5;

    public function handle(PoliteFetcher $fetcher, ExternalIncidentIngestor $ingestor, SourceIngestion $tracking): int
    {
        $run = $tracking->start('knmi_quakes');
        try {
            $query = http_build_query([
                'format' => 'text',
                'starttime' => now()->subHours(6)->utc()->format('Y-m-d\TH:i:s'),
                'minlatitude' => 50.7, 'maxlatitude' => 53.6, 'minlongitude' => 3.3, 'maxlongitude' => 7.25,
                'minmagnitude' => self::MIN_MAGNITUDE,
            ]);
            $body = $fetcher->get(config('services.knmi_quakes.url').'?'.$query);
            [$created, $updated] = [0, 0];
            foreach ($this->parse((string) $body) as $item) {
                $exists = Incident::where('source', 'knmi_quakes')->where('source_reference', $item['id'])->exists();
                $ingestor->quake($item);
                $exists ? $updated++ : $created++;
            }
            $tracking->finish($run, $created + $updated, $created, $updated);
            $this->info('Aardbevingen: '.($created + $updated)." verwerkt ({$created} nieuw).");

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $tracking->fail($run, $exception);
            report($exception);
            $this->error('Aardbevingen mislukt: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    /** FDSN-tekstformaat: één regel per beving, velden gescheiden door '|'. */
    public function parse(string $body): array
    {
        $items = [];
        foreach (preg_split('/\R/', trim($body)) as $line) {
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $field = explode('|', $line);
            if (count($field) < 13 || (float) $field[10] < self::MIN_MAGNITUDE) {
                continue;
            }
            // Alleen bevingen met een Nederlandse plaatsnaam; '(Duitsland)' en '(België)' vallen af.
            if (str_contains($field[12], '(')) {
                continue;
            }
            $magnitude = round((float) $field[10], 1);
            $time = Carbon::parse($field[1], 'UTC');
            $items[] = [
                'id' => $field[0],
                'latitude' => (float) $field[2],
                'longitude' => (float) $field[3],
                'magnitude' => $magnitude,
                'description' => sprintf('Aardbeving bij %s, magnitude %s, diepte %s km.', $field[12], number_format($magnitude, 1, ',', ''), $field[4]),
                'updated_at' => $time->toIso8601String(),
                // Een beving is voorbij, maar blijft een paar uur zichtbaar voor wie hem voelde.
                'ends_at' => $time->copy()->addHours(3)->toIso8601String(),
            ];
        }

        return $items;
    }
}
