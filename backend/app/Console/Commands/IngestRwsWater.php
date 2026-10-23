<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class IngestRwsWater extends Command
{
    protected $signature = 'veiligbuiten:ingest-rws-water';
    protected $description = 'Synchroniseer geselecteerde Rijkswaterstaat-waterstations zodra de actuele endpointconfiguratie is ingevuld.';

    public function handle(): int
    {
        // RWS verving de klassieke WaterWebservices. Geen ongedocumenteerde endpoint gokken:
        // de adapter wordt pas ingeschakeld na een gevalideerde nieuwe WADAR/WaterWebservices-configuratie.
        if (! config('services.rws.water_observations_url')) {
            $this->warn('RWS_WATER_OBSERVATIONS_URL ontbreekt; RWS-import veilig overgeslagen.');
            return self::SUCCESS;
        }

        $this->error('De geconfigureerde RWS-adapter moet eerst per actuele RWS-contracttest worden gevalideerd. Er is geen data geïmporteerd.');
        return self::FAILURE;
    }
}
