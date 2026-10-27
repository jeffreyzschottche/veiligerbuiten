<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
class ResetPasswordNotification extends AccountMailNotification
{
    public function __construct(public string $token)
    {
        parent::__construct();
    }

    public function toMail($notifiable): MailMessage
    {
        $url = rtrim((string) config('app.frontend_url'), '/').'/reset-password?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->email,
            'lang' => $notifiable->locale === 'en' ? 'en' : 'nl',
        ], '', '&', PHP_QUERY_RFC3986);
        $broker = config('auth.defaults.passwords');

        return $this->message($notifiable, 'reset', $url, (int) config("auth.passwords.{$broker}.expire", 60));
    }
}
