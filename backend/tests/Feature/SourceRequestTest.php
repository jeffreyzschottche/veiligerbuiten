<?php

namespace Tests\Feature;

use App\Notifications\SourceRequestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SourceRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_source_request_is_mailed_to_our_address_with_the_user_as_reply_to(): void
    {
        Notification::fake();
        config(['veiligbuiten.source_requests.mail_to' => 'bronnen@example.test']);
        $user = $this->actingAsSubscriber(['email' => 'anna@example.test']);

        $this->postJson('/api/v1/source-requests', ['name' => 'Brandweer Twente', 'url' => 'https://example.org/feed', 'note' => 'Meer meldingen uit Enschede'])
            ->assertAccepted();

        Notification::assertSentTo(new AnonymousNotifiable, SourceRequestNotification::class, function (SourceRequestNotification $notification, array $channels, AnonymousNotifiable $notifiable) use ($user) {
            $mail = $notification->toMail($notifiable);

            return $notifiable->routes['mail'] === 'bronnen@example.test'
                && $mail->replyTo === [['anna@example.test', null]]
                && str_contains($mail->subject, 'Brandweer Twente')
                && $notification->userId === $user->id;
        });
    }

    public function test_a_source_request_needs_a_name_a_real_link_and_an_account(): void
    {
        Notification::fake();
        $this->postJson('/api/v1/source-requests', ['name' => 'X'])->assertUnauthorized();

        $this->actingAsSubscriber();
        $this->postJson('/api/v1/source-requests', ['url' => 'https://example.org'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson('/api/v1/source-requests', ['name' => 'X', 'url' => 'javascript:alert(1)'])->assertUnprocessable()->assertJsonValidationErrors('url');
        Notification::assertNothingSent();
    }
}
