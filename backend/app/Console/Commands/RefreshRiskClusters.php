<?php

namespace App\Console\Commands;

use App\Services\RiskClusters;
use App\Services\AreaSync;
use Illuminate\Console\Command;

class RefreshRiskClusters extends Command
{
    protected $signature = 'veiligbuiten:risk-clusters';
    protected $description = 'Herken plekken waar meerdere ernstige signalen samenkomen.';

    public function handle(RiskClusters $clusters): int
    {
        $updated = $clusters->refresh();
        if ($updated > 0) AreaSync::bump();
        $this->info($updated.' meldingen bijgewerkt.');

        return self::SUCCESS;
    }
}
