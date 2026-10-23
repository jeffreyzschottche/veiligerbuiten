<?php

namespace App\Services;

use App\Models\AlertDelivery;
use App\Models\Installation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushDelivery
{
    public function deliver(AlertDelivery $delivery, Installation $installation): string
    {
        $driver = config('services.push.driver');
        $payload = $delivery->payload;
        $payload['data']['delivery_id'] = (string) $delivery->id;
        if ($driver === 'log') {
            Log::info('VeiligBuiten push (development driver)', ['delivery_id' => $delivery->id, 'platform' => $installation->push_platform, 'payload' => $payload]);
            $delivery->update(['status' => 'simulated', 'sent_at' => now(), 'reason' => 'development_log_driver']);
            return 'simulated';
        }
        if ($driver !== 'fcm' || ! config('services.push.fcm_service_account_json')) {
            $delivery->update(['status' => 'waiting_provider', 'reason' => 'push_provider_not_configured']);
            return 'waiting_provider';
        }
        if ($installation->push_platform !== 'android') {
            $delivery->update(['status' => 'waiting_provider', 'reason' => 'apns_provider_not_configured']);
            return 'waiting_provider';
        }
        try {
            $account = json_decode(base64_decode(config('services.push.fcm_service_account_json')), true, flags: JSON_THROW_ON_ERROR);
            $token = $this->fcmAccessToken($account);
            Http::withToken($token)->acceptJson()->post("https://fcm.googleapis.com/v1/projects/{$account['project_id']}/messages:send", [
                'message' => [
                    'token' => $installation->push_token,
                    'notification' => ['title' => $payload['title'], 'body' => $payload['body']],
                    'data' => array_map('strval', $payload['data']),
                    'android' => ['priority' => 'high', 'notification' => ['channel_id' => 'veiligbuiten_alerts']],
                ],
            ])->throw();
            $delivery->update(['status' => 'sent', 'sent_at' => now(), 'error_message' => null]);
            return 'sent';
        } catch (\Throwable $exception) {
            report($exception);
            $delivery->update(['status' => 'failed', 'failed_at' => now(), 'error_message' => mb_substr($exception->getMessage(), 0, 1000)]);
            return 'failed';
        }
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
