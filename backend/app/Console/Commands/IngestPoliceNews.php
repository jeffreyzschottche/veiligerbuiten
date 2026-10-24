<?php

namespace App\Console\Commands;

use App\Models\Incident;
use App\Services\ExternalIncidentIngestor;
use App\Services\SourceIngestion;
use Carbon\Carbon;
use App\Services\Sources\PoliteFetcher;
use App\Support\DangerKeywords;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class IngestPoliceNews extends Command
{
    protected $signature = 'veiligbuiten:ingest-police-news {--lat= : Eenmalige latitude voor een lokale test} {--lng= : Eenmalige longitude voor een lokale test}';
    protected $description = 'Haal recente, lokale, geolocatie-gekoppelde politieberichten op via de officiële Politie API.';

    /** Politienieuws komt achteraf; ouder dan dit is geen actueel gevaar meer. */
    private const MAX_AGE_HOURS = 12;

    /** De dreiging loopt nog: iemand is voortvluchtig, gewapend of de politie vraagt alert te zijn. */
    private const ONGOING = ['voortvluchtig', 'op de vlucht', 'nog niet aangehouden', 'wees alert', 'niet benaderen', 'bel direct 112', 'bel 112', 'gevaarlijk', 'gewapend', 'vuurwapen', 'afgezet', 'afzetting', 'ontruimd', 'ontruiming', 'gaslek', 'explosief'];

    /** Geen gevaar (meer): aanhoudingen, rechtszaken, cijfers, preventie. */
    private const NOT_A_DANGER = ['aangehouden', 'aanhouding', 'veroordeeld', 'rechtbank', 'rechter', 'cijfers', 'seizoen', 'jaarverslag', 'campagne', 'preventie', 'tips', 'vacature', 'cold case', 'jaar geleden', 'herdenking', 'opgerold', 'in beslag', 'controle', 'oefening', 'vermist', 'teruggevonden', 'overleden aangetroffen'];

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
                        'fromdate' => now()->subHours(self::MAX_AGE_HOURS)->format('Ymd'), 'todate' => now()->format('Ymd'),
                    ];
                    if ($area !== null) $query += ['lat' => $area['lat'], 'lon' => $area['lng'], 'radius' => 25];
                    PoliteFetcher::guard(config('services.politie.news_url'));
                    $response = Http::timeout(25)->acceptJson()->withHeaders(['User-Agent' => PoliteFetcher::USER_AGENT])->get(config('services.politie.news_url'), $query);
                    PoliteFetcher::observe(config('services.politie.news_url'), $response);
                    if ($response->status() === 204) break;
                    $response->throw();
                    foreach ($response->json('nieuwsberichten', []) as $message) {
                    $publishedAt = Carbon::parse($message['publicatieDatum'] ?? $message['publicatiedatum'] ?? now(), config('veiligbuiten.timezone'));
                    if ($publishedAt->lt(now()->subHours(self::MAX_AGE_HOURS))) continue;
                    $id = (string) ($message['uid'] ?? $message['canonicalHandleUuid'] ?? '');
                    $location = $message['locaties'][0] ?? null;
                    $latitude = data_get($location, 'latitude'); $longitude = data_get($location, 'longitude');
                    if ($id === '' || ! is_numeric($latitude) || ! is_numeric($longitude)) continue;

                    $classified = $this->classify((string) ($message['titel'] ?? ''), (string) ($message['introductie'] ?? ''));
                    if ($classified === null) continue;
                    [$category, $subtype, $severity, $hours] = $classified;
                    $item = [
                        'id' => $id, 'latitude' => (float) $latitude, 'longitude' => (float) $longitude,
                        'category' => $category, 'subtype' => $subtype, 'severity' => $severity,
                        'description' => trim((string) ($message['introductie'] ?? 'Actueel lokaal bericht van de politie.')),
                        'updated_at' => $publishedAt->toIso8601String(), 'ends_at' => $publishedAt->copy()->addHours($hours)->toIso8601String(),
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

    /**
     * @return array{0: string, 1: string, 2: int, 3: int}|null [categorie, subtype, ernst, uren zichtbaar];
     *         null als het bericht niet over (lopend) gevaar gaat.
     */
    private function classify(string $title, string $intro): ?array
    {
        $text = DangerKeywords::normalize("{$title} {$intro}");
        $ongoing = $this->mentions($text, self::ONGOING);
        if (! $ongoing && $this->mentions(DangerKeywords::normalize($title), self::NOT_A_DANGER)) {
            return null;
        }

        $match = match (true) {
            // Eerst: zo'n bericht noemt vaak ook 'geweld', maar hoort bij intimidatie.
            $this->mentions($text, ['aanrand', 'aangerand', 'zedendelict', 'zedenmisdrijf', 'seksuele intimidatie', 'straatintimidatie', 'potloodvent', 'exhibitionis', 'schennis', 'achtervolgd', 'lastiggevallen', 'betast']) => ['intimidatie', 'Politiebericht: aanranding of seksuele intimidatie', 3],
            $this->mentions($text, ['schiet', 'schoten', 'vuurwapen', 'steekpartij', 'steekincident', 'neergestoken', 'gestoken', 'overval', 'beroving', 'straatroof', 'mishandel', 'geweld', 'bedreig', 'ontvoer', 'gijzel']) => ['geweld', 'Politiebericht: geweldsincident', 3],
            $this->mentions($text, ['explosie', 'explosief', 'bommelding', 'handgranaat', 'verdacht pakket', 'verdacht object']) => ['onveilig', 'Politiebericht: explosief of verdacht object', 4],
            $this->mentions($text, ['brand', 'rook']) => ['brand', 'Politiebericht: brand', 3],
            $this->mentions($text, ['verward', 'onrustig', 'agressie', 'agressief']) => ['onveilig', 'Politiebericht: verward of agressief persoon', 3],
            $this->mentions($text, ['demonstr', 'onrust', 'rellen', 'relschopper', 'noodbevel', 'noodverordening']) => ['drukte', 'Politiebericht: openbare orde', 3],
            $this->mentions($text, ['ongeval', 'aanrijding', 'doorgereden']) => ['verkeer', 'Politiebericht: verkeersincident', 2],
            $this->mentions($text, ['inbraak', 'inbrek', 'zakkenroller', 'oplichting', 'babbeltruc']) => ['onveilig', 'Politiebericht: inbraak of diefstal', 2],
            default => null,
        };
        if ($match === null) {
            return null;
        }

        // Loopt het nog, dan één stap ernstiger en langer zichtbaar; anders een korte heads-up achteraf.
        return $ongoing ? [$match[0], $match[1], min(5, $match[2] + 1), 6] : [...$match, 4];
    }

    private function mentions(string $normalized, array $words): bool
    {
        return DangerKeywords::first($normalized, $words) !== null;
    }
}
