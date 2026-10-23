<?php

namespace App\Services;

use App\Models\AlertDelivery;
use App\Models\Installation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Levert een alert af: Android via Firebase (FCM HTTP v1), iOS rechtstreeks
 * via APNs. De ernst bepaalt hoe het binnenkomt: gevaar is 'time-sensitive'
 * met geluid, opletten is een gewone melding, goed om te weten is stil.
 */
class PushDelivery
{
    private const ANDROID_CHANNELS = ['danger' => 'vb_danger', 'warn' => 'vb_warn', 'info' => 'vb_info'];

    private const IOS_INTERRUPTION = ['danger' => 'time-sensitive', 'warn' => 'active', 'info' => 'passive'];

    public function deliver(AlertDelivery $delivery, Installation $installation): string
    {
        $payload = $delivery->payload;
        $payload['data']['delivery_id'] = (string) $delivery->id;
        $level = $payload['level'] ?? $delivery->level ?? 'warn';

        if (config('services.push.driver') === 'log') {
            Log::info('VeiligBuiten push (development driver)', ['delivery_id' => $delivery->id, 'platform' => $installation->push_platform, 'payload' => $payload]);
            $delivery->update(['status' => 'simulated', 'sent_at' => now(), 'reason' => 'development_log_driver']);

            return 'simulated';
        }

        try {
            $sent = match ($installation->push_platform) {
                'android' => $this->sendFcm($installation->push_token, $payload, $level),
                'ios' => $this->sendApns($installation->push_token, $payload, $level),
                default => false,
            };
            if (! $sent) {
                $delivery->update(['status' => 'waiting_provider', 'reason' => $installation->push_platform.'_provider_not_configured']);

                return 'waiting_provider';
            }
            $delivery->update(['status' => 'sent', 'sent_at' => now(), 'error_message' => null]);

            return 'sent';
        } catch (\Throwable $exception) {
            report($exception);
            $delivery->update(['status' => 'failed', 'failed_at' => now(), 'error_message' => mb_substr($exception->getMessage(), 0, 1000)]);

            return 'failed';
        }
    }

    private function sendFcm(string $token, array $payload, string $level): bool
    {
        if (! config('services.push.fcm_service_account_json')) {
            return false;
        }
        $account = json_decode(base64_decode(config('services.push.fcm_service_account_json')), true, flags: JSON_THROW_ON_ERROR);

        Http::withToken($this->fcmAccessToken($account))->acceptJson()->post("https://fcm.googleapis.com/v1/projects/{$account['project_id']}/messages:send", [
            'message' => [
                'token' => $token,
                'notification' => ['title' => $payload['title'], 'body' => $payload['body']],
                'data' => array_map('strval', $payload['data']),
                'android' => [
                    'priority' => $level === 'info' ? 'normal' : 'high',
                    'collapse_key' => 'incident-'.$payload['data']['incident_id'],
                    'notification' => ['channel_id' => self::ANDROID_CHANNELS[$level] ?? 'vb_warn', 'tag' => 'incident-'.$payload['data']['incident_id']],
                ],
            ],
        ])->throw();

        return true;
    }

    private function sendApns(string $token, array $payload, string $level): bool
    {
        $config = config('services.apns');
        if (! $config['key_id'] || ! $config['team_id'] || ! $config['private_key']) {
            return false;
        }
        $host = $config['production'] ? 'https://api.push.apple.com' : 'https://api.sandbox.push.apple.com';

        Http::withOptions(['version' => 2.0])
            ->withHeaders([
                'authorization' => 'bearer '.$this->apnsJwt($config),
                'apns-topic' => $config['bundle_id'],
                'apns-push-type' => 'alert',
                'apns-priority' => $level === 'info' ? '5' : '10',
                // Een update van hetzelfde incident vervangt het vorige bericht.
                'apns-collapse-id' => 'incident-'.$payload['data']['incident_id'],
            ])
            ->post("{$host}/3/device/{$token}", [
                'aps' => array_filter([
                    'alert' => ['title' => $payload['title'], 'body' => $payload['body']],
                    'sound' => $level === 'info' ? null : 'default',
                    'interruption-level' => self::IOS_INTERRUPTION[$level] ?? 'active',
                    'relevance-score' => ['danger' => 1.0, 'warn' => 0.6, 'info' => 0.2][$level] ?? 0.5,
                    'thread-id' => 'veiligbuiten',
                ], fn ($value) => $value !== null),
                ...$payload['data'],
            ])->throw();

        return true;
    }

    private function apnsJwt(array $config): string
    {
        // Apple accepteert hetzelfde token tot een uur; we vernieuwen ruim op tijd.
        return Cache::remember('veiligbuiten:apns-jwt', now()->addMinutes(45), function () use ($config) {
            $header = $this->base64Url(json_encode(['alg' => 'ES256', 'kid' => $config['key_id']]));
            $claims = $this->base64Url(json_encode(['iss' => $config['team_id'], 'iat' => now()->timestamp]));
            $key = str_contains($config['private_key'], 'BEGIN PRIVATE KEY') ? $config['private_key'] : base64_decode($config['private_key']);
            if (! openssl_sign("{$header}.{$claims}", $der, $key, OPENSSL_ALGO_SHA256)) {
                throw new \RuntimeException('APNs token could not be signed.');
            }

            return "{$header}.{$claims}.".$this->base64Url($this->derToRaw($der));
        });
    }

    /** OpenSSL geeft een DER-handtekening; JWT (ES256) verwacht R||S van elk 32 bytes. */
    private function derToRaw(string $der): string
    {
        $offset = 2 + (ord($der[1]) & 0x80 ? ord($der[1]) & 0x7f : 0);
        $parts = [];
        for ($i = 0; $i < 2; $i++) {
            $length = ord($der[$offset + 1]);
            $parts[] = str_pad(ltrim(substr($der, $offset + 2, $length), "\x00"), 32, "\x00", STR_PAD_LEFT);
            $offset += 2 + $length;
        }

        return $parts[0].$parts[1];
    }

    private function fcmAccessToken(array $account): string
    {
        return Cache::remember('veiligbuiten:fcm-access-token', now()->addMinutes(50), function () use ($account) {
            $now = now()->timestamp;
            $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claims = $this->base64Url(json_encode(['iss' => $account['client_email'], 'scope' => 'https://www.googleapis.com/auth/firebase.messaging', 'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600]));
            if (! openssl_sign("{$header}.{$claims}", $signature, $account['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new \RuntimeException('FCM service-account signature could not be created.');
            }
            $assertion = "{$header}.{$claims}.".$this->base64Url($signature);

            return Http::asForm()->post('https://oauth2.googleapis.com/token', ['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $assertion])->throw()->json('access_token');
        });
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
