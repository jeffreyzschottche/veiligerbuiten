<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncCbsPoliceMetadata extends Command
{
    protected $signature = 'veiligbuiten:sync-cbs-police-metadata';
    protected $description = 'Reserveer CBS-politiedata voor B2B-analyse; nooit voor live consumentenincidenten.';

    public function handle(): int
    {
        $this->info('CBS-politiedata wordt bewust niet op de realtime VeiligBuiten-kaart geïmporteerd. Configureer eerst een gevalideerde actuele CBS-dataset voor analytics.');
        return self::SUCCESS;
    }
}
