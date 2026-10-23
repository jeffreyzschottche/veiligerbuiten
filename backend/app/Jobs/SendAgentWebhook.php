<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;

/**
 * Stuurt een seintje naar de eigen agent van een gebruiker. De body is
 * ondertekend met HMAC-SHA256 (header X-VeiligBuiten-Signature), zodat de
 * ontvanger kan controleren dat het bericht echt van ons komt.
 */
class SendAgentWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public array $backoff = [10, 60];

    public function __construct(public readonly int $userId, public readonly array $payload) {}

    public function handle(): void
    {
        $user = User::find($this->userId);
        if (! $user?->webhook_url || ! $user->webhook_secret) {
            return;
        }

        $body = json_encode([...$this->payload, 'sent_at' => now()->toIso8601String()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', "{$timestamp}.{$body}", $user->webhook_secret);

        Http::timeout(5)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'User-Agent' => 'VeiligBuiten-Webhook/1.0',
                'X-VeiligBuiten-Timestamp' => $timestamp,
                'X-VeiligBuiten-Signature' => "sha256={$signature}",
            ])
            ->withBody($body, 'application/json')
            ->post($user->webhook_url)
            ->throw();
    }
}
