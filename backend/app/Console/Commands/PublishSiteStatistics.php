<?php

namespace App\Console\Commands;

use App\Services\SiteStatistics;
use Illuminate\Console\Command;

class PublishSiteStatistics extends Command
{
    protected $signature = 'veiligbuiten:publish-site-statistics';
    protected $description = 'Publish a daily database snapshot of the community total';

    public function handle(SiteStatistics $statistics): int
    {
        $statistics->publish();
        $this->info('Community snapshot published.');

        return self::SUCCESS;
    }
}
