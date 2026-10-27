<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ResendTransport;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Resend\Transporters\HttpTransporter;
use Resend\ValueObjects\ApiKey;
use Resend\ValueObjects\Transporter\BaseUri;
use Resend\ValueObjects\Transporter\Headers;
use Tests\TestCase;

class AccountMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.frontend_url' => 'https://veiligerbuiten.nl', 'mail.from.address' => 'hallo@veiligerbuiten.nl', 'mail.from.name' => 'VeiligerBuiten']);
    }

    private function verificationPath(User $user, ?VerifyEmailNotification $notification = null): string
    {
        $url = ($notification ?? new VerifyEmailNotification)->toMail($user)->viewData['url'];
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('veiligerbuiten.nl', parse_url($url, PHP_URL_HOST));
        $this->assertSame('/verify-email', parse_url($url, PHP_URL_PATH));

        return "/api/v1/email/verify/{$query['id']}/{$query['hash']}?".http_build_query(['expires' => $query['expires'], 'signature' => $query['signature']]);
    }

    public function test_registration_sends_one_welcome_with_verification_in_the_account_language(): void
    {
        Notification::fake();
        $this->postJson('/api/v1/register', ['name' => 'Sam', 'email' => 'sam@example.nl', 'password' => 'een-lang-wachtwoord', 'password_confirmation' => 'een-lang-wachtwoord', 'locale' => 'en'])->assertCreated();
        $user = User::sole();
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentToTimes($user, VerifyEmailNotification::class, 1);
        Notification::assertSentTo($user, VerifyEmailNotification::class, function ($notification) use ($user) {
            $this->assertTrue($notification->welcome);
            $this->assertSame('Welcome to VeiligerBuiten · confirm your email', $notification->toMail($user)->subject);
            $this->assertStringContainsString('lang=en', $notification->toMail($user)->viewData['url']);
            return true;
        });
    }

    public function test_account_notifications_are_encrypted_queued_jobs(): void
    {
        Queue::fake();
        $user = User::factory()->unverified()->create();
        $user->sendEmailVerificationNotification();
        $user->sendPasswordResetNotification('private-reset-token');
        Queue::assertPushed(SendQueuedNotifications::class, 2);
        Queue::assertPushed(SendQueuedNotifications::class, fn ($job) => $job->shouldBeEncrypted && $job->tries === 3);
    }

    public function test_verification_works_without_a_login_and_is_idempotent(): void
    {
        Event::fake([Verified::class]);
        $user = User::factory()->unverified()->create();
        $path = $this->verificationPath($user);
        $this->getJson($path)->assertOk();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->getJson($path)->assertOk();
        Event::assertDispatchedTimes(Verified::class, 1);
    }

    public function test_verification_rejects_tampering_expiry_and_a_changed_email_address(): void
    {
        $user = User::factory()->unverified()->create();
        $path = $this->verificationPath($user);
        $this->getJson($path.'x')->assertForbidden();
        $this->travel(61)->minutes();
        $this->getJson($path)->assertForbidden();
        $this->travelBack();
        $user->update(['email' => 'new@example.nl']);
        $this->getJson($path)->assertUnprocessable();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_a_new_confirmation_can_be_requested_and_is_rate_limited(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);
        for ($i = 0; $i < 3; $i++) $this->postJson('/api/v1/email/resend')->assertOk();
        $this->postJson('/api/v1/email/resend')->assertTooManyRequests();
        Notification::assertSentToTimes($user, VerifyEmailNotification::class, 3);
        $this->getJson('/api/v1/me')->assertJsonPath('data.email_verified', false);
    }

    public function test_the_reset_email_contains_a_working_single_use_link(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'sam+reset@example.nl', 'locale' => 'nl']);
        $user->createToken('phone');
        $this->postJson('/api/v1/forgot-password', ['email' => $user->email])->assertOk();
        $notification = Notification::sent($user, ResetPasswordNotification::class)->sole();
        $message = $notification->toMail($user);
        $this->assertSame('Stel een nieuw wachtwoord in · VeiligerBuiten', $message->subject);
        parse_str(parse_url($message->viewData['url'], PHP_URL_QUERY), $query);
        $this->assertSame($user->email, $query['email']);
        $payload = ['email' => $query['email'], 'token' => $query['token'], 'password' => 'Een-nieuw-wachtwoord', 'password_confirmation' => 'Een-nieuw-wachtwoord'];
        $this->postJson('/api/v1/reset-password', $payload)->assertOk();
        $this->assertTrue(Hash::check($payload['password'], $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->postJson('/api/v1/reset-password', $payload)->assertUnprocessable();
    }

    public function test_expired_reset_links_are_rejected(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $token = \Illuminate\Support\Facades\Password::createToken($user);
        $this->travel(61)->minutes();
        $this->postJson('/api/v1/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'Een-nieuw-wachtwoord', 'password_confirmation' => 'Een-nieuw-wachtwoord'])->assertUnprocessable();
    }

    public function test_the_resend_driver_sends_the_branded_html_plain_text_and_inline_logo(): void
    {
        $this->freezeTime();
        config(['mail.default' => 'resend', 'services.resend.key' => 're_test_key']);
        $history = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200, ['Content-Type' => 'application/json'], '{"id":"mail-test-id"}')]));
        $stack->push(Middleware::history($history));
        $client = new \Resend\Client(new HttpTransporter(new HttpClient(['handler' => $stack]), BaseUri::from('api.resend.com'), Headers::withAuthorization(ApiKey::from('re_test_key'))));
        $mailer = Mail::mailer('resend');
        $this->assertInstanceOf(ResendTransport::class, $mailer->getSymfonyTransport());
        $mailer->setSymfonyTransport(new ResendTransport($client));
        $user = User::factory()->unverified()->create(['name' => '<script>alert(1)</script>', 'locale' => 'nl']);
        $notification = new VerifyEmailNotification(welcome: true);

        Notification::sendNow($user, $notification);

        $this->assertCount(1, $history);
        $data = json_decode((string) $history[0]['request']->getBody(), true);
        $from = \Symfony\Component\Mime\Address::create($data['from']);
        $this->assertSame('VeiligerBuiten', $from->getName());
        $this->assertSame('hallo@veiligerbuiten.nl', $from->getAddress());
        $this->assertStringContainsString('Be safe.', $data['html']);
        $this->assertStringContainsString('#2f6bff', $data['html']);
        $this->assertStringContainsString('Bevestig mijn e-mailadres', $data['html']);
        $this->assertStringNotContainsString('<script>', $data['html']);
        $this->assertStringContainsString($notification->toMail($user)->viewData['url'], $data['text']);
        $this->assertStringNotContainsString('&amp;', $data['text']);
        $this->assertCount(1, $data['attachments']);
        $this->assertSame('image/png', $data['attachments'][0]['content_type']);
        $this->assertNotEmpty($data['attachments'][0]['content_id']);
    }
}
