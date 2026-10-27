<?php

namespace App\Console\Commands;

use App\Models\Incident;
use App\Services\ExternalIncidentIngestor;
use App\Services\SourceIngestion;
use App\Services\Sources\PoliteFetcher;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class IngestNdwSafety extends Command
{
    protected $signature = 'veiligbuiten:ingest-ndw {--municipality= : Beperk import tot een ondersteunde gemeente, bijvoorbeeld haarlemmermeer} {--hours= : Alleen meldingen gestart in de laatste N uur}';
    protected $description = 'Haal actuele NDW DATEX II SRTI-veiligheidsmeldingen op.';

    public function handle(ExternalIncidentIngestor $ingestor, SourceIngestion $tracking): int
    {
        $run = $tracking->start('ndw');

        try {
            // Met Last-Modified/ETag: is het bestand niet veranderd, dan een lege 304 in plaats van de hele download.
            $compressed = app(PoliteFetcher::class)->get(config('services.ndw.url'), 30);
            if ($compressed === null) {
                $tracking->finish($run, 0, 0, 0);
                $this->line('NDW: ongewijzigd');

                return self::SUCCESS;
            }
            $xml = gzdecode($compressed);
            if ($xml === false) {
                throw new \RuntimeException('NDW-feed is geen geldig gzip-bestand.');
            }
            $document = simplexml_load_string($xml);
            if ($document === false) {
                throw new \RuntimeException('NDW-feed bevat geen geldige XML.');
            }

            $seen = collect();
            $created = 0;
            $updated = 0;

            foreach ($document->xpath('//*[local-name()="situation"]') ?: [] as $situation) {
                foreach ($situation->xpath('./*[local-name()="situationRecord"]') ?: [] as $record) {
                    $item = $this->normalise($situation, $record);
                    if ($item === null || ! $this->withinScope($item)) {
                        continue;
                    }

                    $seen->push($item['id']);
                    $exists = Incident::query()->where('source', 'ndw')->where('source_reference', $item['id'])->exists();
                    $ingestor->ndw($item);
                    $tracking->see('ndw', $item['id'], $item);
                    $exists ? $updated++ : $created++;
                }
            }

            // NDW is a snapshot; only resolve after a full next five-minute interval without it.
            // A manually narrowed import is not a complete NDW snapshot and may never reconcile nationwide records.
            $resolved = $this->option('municipality') || $this->option('hours')
                ? 0
                : $tracking->reconcile('ndw', $seen, 5);
            $tracking->finish($run, $seen->count(), $created, $updated);
            $this->info("{$seen->count()} NDW-records verwerkt ({$created} nieuw, {$updated} bijgewerkt, {$resolved} opgelost).");

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $tracking->fail($run, $exception);
            report($exception);
            $this->error('NDW-import mislukt: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    private function normalise(\SimpleXMLElement $situation, \SimpleXMLElement $record): ?array
    {
        $recordId = trim((string) $record['id']);
        $situationId = trim((string) $situation['id']);
        if ($recordId === '' && $situationId === '') {
            return null;
        }

        $coordinates = $record->xpath('.//*[local-name()="pointCoordinates"]') ?: [];
        $latitude = $this->xpathText($coordinates[0] ?? null, './*[local-name()="latitude"]');
        $longitude = $this->xpathText($coordinates[0] ?? null, './*[local-name()="longitude"]');
        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }

        $xsi = $record->attributes('http://www.w3.org/2001/XMLSchema-instance');
        $type = (string) ($xsi['type'] ?? '');
        // mobilityType (bijv. "stationary") is geen incidenttype. Lees de inhoudelijke types expliciet.
        $detailNodes = $record->xpath('.//*[local-name()="vehicleObstructionType" or local-name()="obstructionType" or local-name()="accidentType" or local-name()="roadOrCarriagewayOrLaneManagementType" or local-name()="roadMaintenanceType" or local-name()="poorEnvironmentType" or local-name()="roadSurfaceConditionType"]') ?: [];
        $subtypes = array_values(array_filter(array_map(fn ($node) => trim((string) $node), $detailNodes)));
        $comment = $this->xpathText($record, './/*[local-name()="generalPublicComment"]//*[local-name()="value"][@lang="nl"]')
            ?? $this->xpathText($record, './/*[local-name()="generalPublicComment"]//*[local-name()="value"]') ?? '';
        $start = $this->xpathText($record, './/*[local-name()="overallStartTime"]')
            ?? $this->xpathText($record, './*[local-name()="situationRecordCreationTime"]')
            ?? now()->toIso8601String();
        $end = $this->xpathText($record, './/*[local-name()="overallEndTime"]');

        return [
            'id' => $recordId !== '' ? $recordId : $situationId,
            'type' => $type,
            'subtype' => $subtypes[0] ?? '',
            'subtypes' => $subtypes,
            'road' => $this->xpathText($record, './/*[local-name()="roadNumber"]'),
            'carriageway' => $this->xpathText($record, './/*[local-name()="carriageway"][not(*)]'),
            'latitude' => (float) $latitude,
            'longitude' => (float) $longitude,
            'comment' => trim($comment),
            'updated_at' => $start,
            'ends_at' => $end,
        ];
    }

    private function xpathText(?\SimpleXMLElement $node, string $query): ?string
    {
        if ($node === null) {
            return null;
        }
        $result = $node->xpath($query) ?: [];
        $value = trim((string) ($result[0] ?? ''));

        return $value !== '' ? $value : null;
    }

    private function withinScope(array $item): bool
    {
        if ($hours = $this->option('hours')) {
            if (Carbon::parse($item['updated_at'])->lt(now()->subHours((int) $hours))) {
                return false;
            }
        }
        $municipality = strtolower((string) $this->option('municipality'));
        if ($municipality === '') {
            return true;
        }

        return match ($municipality) {
            'haarlemmermeer' => $item['latitude'] >= 52.18 && $item['latitude'] <= 52.43 && $item['longitude'] >= 4.55 && $item['longitude'] <= 4.89,
            default => false,
        };
    }
}
