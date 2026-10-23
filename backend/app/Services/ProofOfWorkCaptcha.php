<?php

namespace App\Services;

use Illuminate\Support\Str;

class ProofOfWorkCaptcha
{
    private const DIFFICULTY = 3;

    public function challenge(): array
    {
        $salt = Str::random(32);
        $expiresAt = now()->addMinutes(5)->timestamp;
        $signature = $this->signature($salt, $expiresAt, self::DIFFICULTY);

        return compact('salt', 'expiresAt', 'signature') + ['difficulty' => self::DIFFICULTY];
    }

    public function valid(string $token): bool
    {
        [$salt, $expiresAt, $difficulty, $signature, $nonce] = array_pad(explode('.', $token, 5), 5, null);

        if (! $salt || ! ctype_digit((string) $expiresAt) || ! ctype_digit((string) $difficulty) || ! $signature || ! ctype_digit((string) $nonce)) return false;
        if ((int) $expiresAt < now()->timestamp || (int) $difficulty !== self::DIFFICULTY) return false;
        if (! hash_equals($this->signature($salt, (int) $expiresAt, (int) $difficulty), $signature)) return false;

        return str_starts_with(hash('sha256', "{$salt}:{$nonce}"), str_repeat('0', (int) $difficulty));
    }

    private function signature(string $salt, int $expiresAt, int $difficulty): string
    {
        return hash_hmac('sha256', "{$salt}|{$expiresAt}|{$difficulty}", config('app.key'));
    }
}
