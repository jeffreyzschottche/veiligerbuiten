<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends AccountMailNotification
{
    public function __construct(public bool $welcome = false)
    {
        parent::__construct();
    }

    public function toMail($notifiable): MailMessage
    {
        $minutes = (int) config('auth.verification.expire', 60);
        // Relatief ondertekend: werkt ook via de same-origin gateway van de website.
        $signed = URL::temporarySignedRoute('verification.verify', now()->addMinutes($minutes), [
            'id' => $notifiable->getKey(),
            'hash' => sha1($notifiable->getEmailForVerification()),
        ], absolute: false);
        parse_str((string) parse_url($signed, PHP_URL_QUERY), $signature);
        $url = rtrim((string) config('app.frontend_url'), '/').'/verify-email?'.http_build_query([
            'id' => $notifiable->getKey(),
            'hash' => sha1($notifiable->getEmailForVerification()),
            'expires' => $signature['expires'],
            'signature' => $signature['signature'],
            'lang' => $notifiable->locale === 'en' ? 'en' : 'nl',
        ], '', '&', PHP_QUERY_RFC3986);

        return $this->message($notifiable, $this->welcome ? 'welcome' : 'verify', $url, $minutes);
    }

    public function shouldSend($notifiable, string $channel): bool
    {
        return $this->welcome || ! $notifiable->hasVerifiedEmail();
    }
}
