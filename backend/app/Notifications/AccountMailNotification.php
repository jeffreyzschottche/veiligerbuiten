<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

abstract class AccountMailNotification extends Notification implements ShouldQueue, ShouldBeEncrypted
{
    use Queueable;

    public int $tries = 3;

    public function __construct()
    {
        $this->afterCommit();
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    protected function message($user, string $kind, string $url, int $minutes): MailMessage
    {
        $locale = $user->locale === 'en' ? 'en' : 'nl';
        $copy = trans('account-mail.'.$kind, [], $locale);

        return (new MailMessage)
            ->subject($copy['subject'])
            ->view(['html' => 'emails.account', 'text' => 'emails.account-text'], [
                'locale' => $locale,
                'name' => $user->name,
                'email' => $user->email,
                'copy' => $copy,
                'common' => trans('account-mail.common', [], $locale),
                'url' => $url,
                'minutes' => $minutes,
                'frontend' => rtrim((string) config('app.frontend_url'), '/'),
            ]);
    }
}
