<?php

namespace App\Console\Commands;

use App\Services\ExternalIncidentIngestor;
use App\Services\P2000FeedService;
use App\Services\SourceIngestion;
use Illuminate\Console\Command;

class IngestP2000 extends Command
{
    protected $signature = 'veiligbuiten:ingest-p2000
                            {--feed=all : Which feed to use (zwaailicht, alarmeringen, or all)}
                            {--dry-run : Show what would be imported without saving}';

    protected $description = 'Haal P2000 meldingen op van RSS/Atom feeds en normaliseer ze tot incidenten.';

    public function handle(
        P2000FeedService $feedService,
        ExternalIncidentIngestor $ingestor,
        SourceIngestion $tracking
    ): int {
        $feed = $this->option('feed');
        $dryRun = $this->option('dry-run');

        $run = $tracking->start('p2000');
        $count = 0;
        $created = 0;
        $updated = 0;
        $seen = collect();

        try {
            // Fetch from Zwaailicht (primary - has geo coordinates)
            if ($feed === 'all' || $feed === 'zwaailicht') {
                $this->info('Fetching Zwaailicht.nl feed...');
                $items = $feedService->fetchZwaailicht();
                if ($items === null) {
                    // 304: niets nieuws. Niet reconciliëren, anders lijken lopende meldingen verdwenen.
                    $this->line('  Feed unchanged (304)');
                    $tracking->finish($run, 0, 0, 0);

                    return self::SUCCESS;
                }
                $this->line("  Found {$items->count()} items with coordinates");

                foreach ($items as $item) {
                    if ($dryRun) {
                        $this->line("  [DRY-RUN] Would import: {$item['title']} ({$item['category']}/{$item['subtype']})");
                        $count++;
                        continue;
                    }

                    $exists = \App\Models\Incident::query()
                        ->where('source', 'p2000_zwaailicht')
                        ->where('source_reference', $item['id'])
                        ->exists();

                    $ingestor->p2000($item);
                    $tracking->see('p2000_zwaailicht', $item['id'], $item);
                    $seen->push($item['id']);

                    $exists ? $updated++ : $created++;
                    $count++;
                }
            }

            // Alarmeringen feed (no geo coords - skip for now, could add geocoding later)
            if ($feed === 'alarmeringen') {
                $this->warn('Alarmeringen.nl feed has no coordinates - skipping.');
                $this->line('Use --feed=zwaailicht or --feed=all for geo-located incidents.');
            }

            if (!$dryRun) {
                // Geen reconcile: de feed is een rollend venster (ongeveer de laatste 50 meldingen),
                // geen momentopname. Een melding die eruit schuift is niet voorbij; de levensduur
                // volgt de categorie (IncidentLifecycle) en nieuwe berichten over dezelfde plek.
                $tracking->finish($run, $count, $created, $updated);
            }

            $this->info("P2000 import voltooid: {$count} meldingen verwerkt ({$created} nieuw, {$updated} bijgewerkt).");

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $tracking->fail($run, $exception);
            report($exception);
            $this->error('P2000 import mislukt: ' . $exception->getMessage());

            return self::FAILURE;
        }
    }
}
