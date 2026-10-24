<?php

namespace App\Jobs;

use App\Models\User;
use App\Support\SafeUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;

/**
 * Stuurt een seintje naar de eigen agent van een gebruiker. De body is
 * ondertekend met HMAC-SHA256 (header X-VeiligerBuiten-Signature), zodat de
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

        // Elke keer opnieuw controleren: DNS kan sinds het opslaan veranderd zijn.
        $target = SafeUrl::resolve($user->webhook_url);
        if (! $target) {
            return;
        }

        $body = json_encode([...$this->payload, 'sent_at' => now()->toIso8601String()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', "{$timestamp}.{$body}", $user->webhook_secret);

        Http::timeout(5)
            // Vastgepind op het gecontroleerde IP en geen redirects: een redirect kan naar een intern adres wijzen.
            ->withOptions(['allow_redirects' => false, 'curl' => [CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:{$target['ip']}"]]])
            ->withHeaders([
                'Content-Type' => 'application/json',
                'User-Agent' => 'VeiligerBuiten-Webhook/1.0',
                'X-VeiligerBuiten-Timestamp' => $timestamp,
                'X-VeiligerBuiten-Signature' => "sha256={$signature}",
            ])
            ->withBody($body, 'application/json')
            ->post($user->webhook_url)
            ->throw();
    }
}
