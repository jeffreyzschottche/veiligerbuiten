<?php

namespace App\Console\Commands;

use App\Models\Incident;
use App\Services\ExternalIncidentIngestor;
use App\Services\SourceIngestion;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class IngestPoliceNews extends Command
{
    protected $signature = 'veiligbuiten:ingest-police-news {--lat= : Eenmalige latitude voor een lokale test} {--lng= : Eenmalige longitude voor een lokale test}';
    protected $description = 'Haal recente, lokale, geolocatie-gekoppelde politieberichten op via de officiële Politie API.';

    public function handle(ExternalIncidentIngestor $ingestor, SourceIngestion $tracking): int
    {
        if (! config('services.politie.enabled')) {
            $this->warn('POLITIE_NEWS_ENABLED staat niet aan; import overgeslagen.');
            return self::SUCCESS;
        }

            $run = $tracking->start('politie');
        try {
            $areas = $this->areas(); $seen = collect(); $created = 0; $updated = 0;
            foreach ($areas as $area) {
                for ($offset = 0; $offset < 200; $offset += 25) {
                    $query = [
                        'language' => 'nl', 'maxnumberofitems' => 25, 'offset' => $offset,
                        'fromdate' => now()->subDay()->format('Ymd'), 'todate' => now()->format('Ymd'),
                    ];
                    if ($area !== null) $query += ['lat' => $area['lat'], 'lon' => $area['lng'], 'radius' => 25];
                    $response = Http::timeout(25)->retry(2, 500)->acceptJson()->get(config('services.politie.news_url'), $query);
                    if ($response->status() === 204) break;
                    $response->throw();
                    foreach ($response->json('nieuwsberichten', []) as $message) {
                    $publishedAt = Carbon::parse($message['publicatieDatum'] ?? $message['publicatiedatum'] ?? now());
                    if ($publishedAt->lt(now()->subDay())) continue;
                    $id = (string) ($message['uid'] ?? $message['canonicalHandleUuid'] ?? '');
                    $location = $message['locaties'][0] ?? null;
                    $latitude = data_get($location, 'latitude'); $longitude = data_get($location, 'longitude');
                    if ($id === '' || ! is_numeric($latitude) || ! is_numeric($longitude)) continue;

                    [$category, $subtype, $severity] = $this->classify((string) ($message['titel'] ?? ''), (string) ($message['introductie'] ?? ''));
                    $item = [
                        'id' => $id, 'latitude' => (float) $latitude, 'longitude' => (float) $longitude,
                        'category' => $category, 'subtype' => $subtype, 'severity' => $severity,
                        'description' => trim((string) ($message['introductie'] ?? 'Actueel lokaal bericht van de politie.')),
                        'updated_at' => $publishedAt->toIso8601String(), 'ends_at' => $publishedAt->addDay()->toIso8601String(),
                        'url' => $message['url'] ?? null, 'title' => $message['titel'] ?? null,
                    ];
                    $seen->push($id);
                    $exists = Incident::query()->where('source', 'politie')->where('source_reference', $id)->exists();
                    $ingestor->politie($item); $tracking->see('politie', $id, $item);
                    $exists ? $updated++ : $created++;
                }
                    if ((bool) $response->json('iterator.last', true)) break;
                }
            }
            // Every configured area together forms the intended coverage snapshot.
            $tracking->reconcile('politie', $seen, 60);
            $tracking->finish($run, $seen->count(), $created, $updated);
            $this->info("{$seen->count()} actuele, geolocatie-gekoppelde politieberichten verwerkt ({$created} nieuw, {$updated} bijgewerkt).");
            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $tracking->fail($run, $exception); report($exception);
            $this->error('Politie-import mislukt: '.$exception->getMessage());
            return self::FAILURE;
        }
    }

    private function areas(): array
    {
        if ($this->option('lat') !== null && $this->option('lng') !== null) {
            return [['lat' => (float) $this->option('lat'), 'lng' => (float) $this->option('lng')]];
        }
        return config('services.politie.nationwide') ? [null] : config('services.politie.coverage_areas');
    }

    private function classify(string $title, string $intro): array
    {
        $text = mb_strtolower("{$title} {$intro}");
        return match (true) {
            str_contains($text, 'brand') || str_contains($text, 'rook') || str_contains($text, 'explos') => ['brand', 'Politiebericht: brand / explosie', 4],
            str_contains($text, 'ongeval') || str_contains($text, 'aanrijding') || str_contains($text, 'verkeers') => ['verkeer', 'Politiebericht: verkeersincident', 3],
            str_contains($text, 'beroving') || str_contains($text, 'overval') || str_contains($text, 'steek') || str_contains($text, 'schiet') || str_contains($text, 'mishandel') || str_contains($text, 'geweld') => ['geweld', 'Politiebericht: geweldsincident', 4],
            str_contains($text, 'demonstr') || str_contains($text, 'onrust') || str_contains($text, 'rell') => ['drukte', 'Politiebericht: openbare orde', 3],
            default => ['onveilig', 'Politiebericht', 2],
        };
    }
}
