<?php

namespace App\Console\Commands;

use App\Services\AlertEngine;
use Illuminate\Console\Command;

class FlushAlertDigests extends Command
{
    protected $signature = 'veiligbuiten:flush-digests';
    protected $description = 'Bundel wachtende "goed om te weten"-meldingen tot één stil bericht per gebruiker.';

    public function handle(AlertEngine $alerts): int
    {
        $this->info($alerts->flushDigests().' gebundelde berichten in de wachtrij geplaatst.');

        return self::SUCCESS;
    }
}
