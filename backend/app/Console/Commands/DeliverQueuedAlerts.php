<?php

namespace App\Console\Commands;

use App\Models\AlertDelivery;
use App\Models\Installation;
use App\Services\PushDelivery;
use Illuminate\Console\Command;

class DeliverQueuedAlerts extends Command
{
    protected $signature = 'veiligbuiten:deliver-alerts';
    protected $description = 'Lever wachtrij-alerts af via de geconfigureerde pushprovider.';

    public function handle(PushDelivery $push): int
    {
        $count = 0;
        foreach (AlertDelivery::query()->where('status', 'queued')->orderBy('id')->limit(100)->cursor() as $delivery) {
            $installation = Installation::find($delivery->installation_id);
            if (! $installation?->push_token) { $delivery->update(['status' => 'failed', 'failed_at' => now(), 'error_message' => 'Missing push token']); continue; }
            $push->deliver($delivery, $installation);
            $count++;
        }
        $this->info("{$count} queued alerts verwerkt.");
        return self::SUCCESS;
    }
}
