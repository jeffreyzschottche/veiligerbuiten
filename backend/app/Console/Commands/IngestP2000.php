<?php

namespace App\Console\Commands;

use App\Models\Incident;
use App\Services\AreaSync;
use App\Services\ExternalIncidentIngestor;
use App\Services\IncidentLifecycle;
use App\Services\P2000FeedService;
use App\Services\SourceIngestion;
use App\Services\Sources\P2000ExtraFeeds;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * P2000 uit meerdere bronnen: Zwaailicht (met coördinaten), Sirenes, Alarmeringen.nl
 * en de P2000-monitor. Hetzelfde voorval uit meerdere bronnen wordt één melding.
 *
 * Geen reconcile: alle feeds zijn rollende vensters (de laatste ~50 meldingen), geen
 * momentopname. Een melding die eruit schuift is niet voorbij; de levensduur volgt de
 * categorie (IncidentLifecycle) en nieuwe berichten over dezelfde plek.
 */
class IngestP2000 extends Command
{
    protected $signature = 'veiligbuiten:ingest-p2000
                            {--feed=all : zwaailicht, sirenes, alarmeringen, monitor of all}
                            {--dry-run : Toon wat geïmporteerd zou worden zonder op te slaan}';

    protected $description = 'Haal P2000-meldingen op uit alle bronnen en normaliseer ze tot incidenten.';

    public function handle(P2000FeedService $zwaailicht, P2000ExtraFeeds $extra, ExternalIncidentIngestor $ingestor, SourceIngestion $tracking): int
    {
        $wanted = $this->option('feed');
        $feeds = [];
        // Ook bij --feed=zwaailicht of all: uit is uit (zie ZWAAILICHT_ENABLED).
        if (config('veiligbuiten.scrape.sources.p2000_zwaailicht.enabled', false)) {
            $feeds['zwaailicht'] = fn () => $zwaailicht->fetchZwaailicht();
        } elseif ($wanted === 'zwaailicht') {
            $this->warn('zwaailicht: staat uit (ZWAAILICHT_ENABLED=false)');
        }
        if (! $this->option('dry-run') && app(IncidentLifecycle::class)->archiveDisabledSources() > 0) {
            AreaSync::bump();
        }
        foreach (array_keys($extra->feeds()) as $name) {
            $feeds[$name] = fn () => $extra->fetch($name);
        }

        $failed = false;
        foreach ($feeds as $name => $fetch) {
            if ($wanted !== 'all' && $wanted !== $name) {
                continue;
            }
            $failed = ! $this->import($name, $fetch, $ingestor, $tracking) || $failed;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /** Eén bron; een fout in de ene bron houdt de andere niet tegen. */
    private function import(string $name, \Closure $fetch, ExternalIncidentIngestor $ingestor, SourceIngestion $tracking): bool
    {
        $source = "p2000_{$name}";
        $run = $tracking->start($name === 'zwaailicht' ? 'p2000' : $source);
        try {
            /** @var Collection|null $items */
            $items = $fetch();
            if ($items === null) {
                // 304: niets nieuws.
                $tracking->finish($run, 0, 0, 0);
                $this->line("{$name}: ongewijzigd");

                return true;
            }

            [$created, $updated, $skipped] = [0, 0, 0];
            foreach ($items as $item) {
                if ($this->option('dry-run')) {
                    $this->line("  [{$name}] {$item['title']} ({$item['category']}/{$item['subtype']}, ernst {$item['severity']})");
                    continue;
                }
                $exists = Incident::query()->where('source', $source)->where('source_reference', $item['id'])->exists();
                $incident = $ingestor->p2000($item);
                if (! $incident) {
                    $skipped++;
                    continue;
                }
                $tracking->see($source, $item['id'], $item);
                $exists ? $updated++ : $created++;
            }

            $tracking->finish($run, $items->count(), $created, $updated);
            $this->info("{$name}: {$items->count()} meldingen ({$created} nieuw, {$updated} bijgewerkt, {$skipped} alleen ter bevestiging zonder match).");

            return true;
        } catch (\Throwable $exception) {
            $tracking->fail($run, $exception);
            report($exception);
            $this->error("{$name} mislukt: ".$exception->getMessage());

            return false;
        }
    }
}
