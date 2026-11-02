<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Een gebruiker vraagt een nieuwe bron aan; de mail gaat naar ons eigen adres (SOURCE_REQUEST_MAIL). */
class SourceRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @param array{name: string, url?: ?string, note?: ?string} $request */
    public function __construct(public readonly int $userId, public readonly string $userEmail, public readonly array $request) {}

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Bronaanvraag: '.$this->request['name'])
            ->replyTo($this->userEmail)
            ->greeting('Nieuwe bronaanvraag')
            ->line('**Bron:** '.$this->request['name']);
        if (! empty($this->request['url'])) {
            $message->line('**Website:** '.$this->request['url']);
        }
        if (! empty($this->request['note'])) {
            $message->line('**Toelichting:** '.$this->request['note']);
        }

        return $message->line('Aangevraagd door gebruiker #'.$this->userId.' ('.$this->userEmail.'). Beantwoorden gaat rechtstreeks naar deze gebruiker.');
    }
}
