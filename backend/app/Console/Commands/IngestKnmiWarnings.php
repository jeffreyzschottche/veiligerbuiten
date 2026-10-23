<?php

namespace App\Console\Commands;

use App\Services\ExternalIncidentIngestor;
use App\Services\SourceIngestion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class IngestKnmiWarnings extends Command
{
    protected $signature = 'veiligbuiten:ingest-knmi';
    protected $description = 'Haal actuele KNMI-waarschuwingen op en normaliseer ze tot incidenten.';

    public function handle(ExternalIncidentIngestor $ingestor, SourceIngestion $tracking): int
    {
        $key = config('services.knmi.key');
        if (!$key) { $this->warn('KNMI_API_KEY ontbreekt; KNMI-import overgeslagen.'); return self::SUCCESS; }
        $run = $tracking->start('knmi');
        try {
            $base = 'https://api.dataplatform.knmi.nl/open-data/v1/datasets/waarschuwingen_nederland_48h/versions/1.0/files';
            $files = Http::withToken($key)->acceptJson()->get($base)->throw()->json('files', []);
            $filename = collect($files)->pluck('filename')->filter(fn ($name) => str_ends_with(strtolower($name), '.xml'))->sort()->last();
            if (!$filename) throw new \RuntimeException('KNMI leverde geen XML-waarschuwingbestand.');
            $downloadUrl = Http::withToken($key)->acceptJson()->get($base.'/'.rawurlencode($filename).'/url')->throw()->json('temporaryDownloadUrl');
            $xml = simplexml_load_string(Http::timeout(20)->get($downloadUrl)->throw()->body());
            if ($xml === false) throw new \RuntimeException('KNMI-waarschuwingbestand bevat geen geldige XML.');
            $count = 0; $created = 0; $updated = 0; $seen = collect();
            foreach ($xml->xpath('//*[local-name()="warning" or local-name()="waarschuwing"]') ?: [] as $warning) {
                $attributes = $warning->attributes();
                $region = trim((string) (($warning->xpath('.//*[local-name()="region" or local-name()="gebied"]')[0] ?? 'Nederland')));
                $level = strtolower(trim((string) ($attributes['level'] ?? $attributes['colour'] ?? $attributes['color'] ?? 'yellow')));
                $title = trim((string) (($warning->xpath('.//*[local-name()="event" or local-name()="phenomenon" or local-name()="verschijnsel"]')[0] ?? 'Officiële weerwaarschuwing')));
                $description = trim((string) (($warning->xpath('.//*[local-name()="description" or local-name()="tekst"]')[0] ?? 'Officiële waarschuwing van het KNMI.')));
                $id = (string) ($attributes['id'] ?? hash('sha256', $filename.'|'.$region.'|'.$title));
                [$latitude, $longitude] = $this->centroid($region);
                $exists = \App\Models\Incident::query()->where('source', 'knmi')->where('source_reference', $id)->exists();
                $ingestor->knmi(compact('id', 'level', 'title', 'description', 'latitude', 'longitude'));
                $tracking->see('knmi', $id, compact('id', 'level', 'title', 'description', 'latitude', 'longitude'));
                $seen->push($id); $exists ? $updated++ : $created++;
                $count++;
            }
            $tracking->reconcile('knmi', $seen, 60);
            $tracking->finish($run, $count, $created, $updated);
            $this->info("{$count} KNMI-waarschuwingen genormaliseerd."); return self::SUCCESS;
        } catch (\Throwable $exception) { $tracking->fail($run, $exception); report($exception); $this->error('KNMI-import mislukt: '.$exception->getMessage()); return self::FAILURE; }
    }

    private function centroid(string $region): array
    {
        $regions = ['noord-holland' => [52.52, 4.79], 'zuid-holland' => [51.97, 4.48], 'utrecht' => [52.10, 5.12], 'flevoland' => [52.53, 5.47], 'zeeland' => [51.50, 3.81], 'noord-brabant' => [51.59, 5.30], 'limburg' => [50.95, 5.88], 'gelderland' => [52.04, 5.91], 'overijssel' => [52.44, 6.44], 'drenthe' => [52.89, 6.62], 'friesland' => [53.12, 5.74], 'groningen' => [53.22, 6.57]];
        $key = strtolower(str_replace('ë', 'e', $region)); return $regions[$key] ?? [52.1326, 5.2913];
    }
}
