<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\Installation;
use App\Models\User;
use App\Services\ExternalIncidentIngestor;
use App\Support\SafeUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        SafeUrl::fakeDns([]);
        parent::tearDown();
    }

    private function incident(array $overrides = []): Incident
    {
        return Incident::create([
            'category' => 'geweld', 'subtype' => 'Vechtpartij', 'status' => 'gemeld', 'visibility' => 'public', 'source' => 'user',
            'severity' => 4, 'confidence' => .2, 'incident_type' => 'event', 'confirmations_count' => 1,
            'latitude' => 52.3702, 'longitude' => 4.8952, 'last_confirmed_at' => now(), 'last_activity_at' => now(), 'expires_at' => now()->addHour(),
            ...$overrides,
        ]);
    }

    public function test_webhooks_can_never_point_at_internal_addresses(): void
    {
        $this->actingAsSubscriber();
        SafeUrl::fakeDns(['agent.example' => ['93.184.216.34'], 'sneaky.example' => ['10.0.0.5']]);

        foreach (['https://127.0.0.1/hook', 'https://localhost/hook', 'https://169.254.169.254/latest/meta-data', 'https://10.1.2.3/x', 'https://192.168.1.10/x', 'https://[::1]/x', 'https://100.64.0.1/x', 'https://sneaky.example/hook', 'https://user:pass@agent.example/hook'] as $url) {
            $this->putJson('/api/v1/me/ai/webhook', ['webhook_url' => $url])->assertUnprocessable();
        }
        $this->putJson('/api/v1/me/ai/webhook', ['webhook_url' => 'https://agent.example/hook'])->assertOk();
    }

    public function test_a_webhook_whose_dns_later_points_inward_is_not_called_and_reveals_nothing(): void
    {
        $user = $this->actingAsSubscriber();
        SafeUrl::fakeDns(['agent.example' => ['93.184.216.34']]);
        $this->putJson('/api/v1/me/ai/webhook', ['webhook_url' => 'https://agent.example/hook'])->assertOk();
        Http::fake(['*' => Http::response('nope', 500)]);

        SafeUrl::fakeDns(['agent.example' => ['127.0.0.1']]);
        $this->postJson('/api/v1/me/ai/webhook/test')->assertOk();
        Http::assertNothingSent();

        SafeUrl::fakeDns(['agent.example' => ['93.184.216.34']]);
        $this->postJson('/api/v1/me/ai/webhook/test')->assertOk()->assertJsonPath('data.sent', false)->assertJsonMissingPath('message');
    }

    public function test_extra_installations_do_not_give_one_account_extra_votes(): void
    {
        $user = $this->actingAsSubscriber();
        $incident = $this->incident();
        $first = Installation::create(['token' => str_repeat('a', 64), 'trust_score' => 50]);
        $second = Installation::create(['token' => str_repeat('b', 64), 'trust_score' => 50]);

        $this->postJson("/api/v1/incidents/{$incident->id}/confirm", [], ['X-Installation-Token' => $first->token])->assertOk();
        $this->postJson("/api/v1/incidents/{$incident->id}/confirm", [], ['X-Installation-Token' => $second->token])->assertStatus(409);
        $this->postJson("/api/v1/incidents/{$incident->id}/resolve", [], ['X-Installation-Token' => $second->token])->assertStatus(409);

        $this->assertSame(2, $incident->fresh()->confirmations_count);
    }

    public function test_someone_elses_installation_cannot_be_used_to_vote(): void
    {
        $other = User::factory()->create();
        $this->actingAsSubscriber();
        $installation = Installation::create(['token' => str_repeat('o', 64), 'trust_score' => 50, 'user_id' => $other->id]);

        $this->postJson("/api/v1/incidents/{$this->incident()->id}/confirm", [], ['X-Installation-Token' => $installation->token])->assertForbidden();
    }

    public function test_reporting_the_same_situation_twice_does_not_count_double(): void
    {
        $user = $this->actingAsSubscriber();
        $report = fn (string $token) => $this->postJson('/api/v1/reports', [
            'category' => 'verkeer', 'subtype' => 'Verkeersongeval', 'latitude' => 52.3702, 'longitude' => 4.8952,
            'user_latitude' => 52.3705, 'user_longitude' => 4.8952, 'location_accuracy_m' => 15,
        ], ['X-Installation-Token' => $token]);
        $first = Installation::create(['token' => str_repeat('r', 64), 'trust_score' => 50, 'user_id' => $user->id]);
        $second = Installation::create(['token' => str_repeat('s', 64), 'trust_score' => 50, 'user_id' => $user->id]);

        $id = $report($first->token)->assertCreated()->json('data.id');
        $report($second->token)->assertStatus(409);
        $this->postJson("/api/v1/incidents/{$id}/confirm", [], ['X-Installation-Token' => $second->token])->assertStatus(409);

        $this->assertSame(1, Incident::find($id)->confirmations_count);
        $this->getJson('/api/v1/reports/quota')->assertJsonPath('data.remaining', 2);
    }

    public function test_password_reset_does_not_reveal_which_emails_have_an_account(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'bestaat@example.com']);

        $known = $this->postJson('/api/v1/forgot-password', ['email' => 'bestaat@example.com'])->assertOk()->json('message');
        $unknown = $this->postJson('/api/v1/forgot-password', ['email' => 'bestaatniet@example.com'])->assertOk()->json('message');

        $this->assertSame($known, $unknown);
    }

    public function test_a_password_reset_logs_out_every_device(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com']);
        $user->createToken('phone');
        $token = Password::createToken($user);

        $this->postJson('/api/v1/reset-password', ['email' => 'reset@example.com', 'token' => $token, 'password' => 'Nieuw-wachtwoord-123', 'password_confirmation' => 'Nieuw-wachtwoord-123'])->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_one_account_cannot_be_brute_forced_from_many_addresses(): void
    {
        User::factory()->create(['email' => 'doelwit@example.com']);

        foreach (range(1, 5) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => "203.0.113.{$i}"])
                ->postJson('/api/v1/login', ['email' => 'doelwit@example.com', 'password' => 'fout'])->assertUnprocessable();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.99'])
            ->postJson('/api/v1/login', ['email' => 'doelwit@example.com', 'password' => 'fout'])->assertStatus(429);
    }

    public function test_changing_the_email_address_requires_the_password(): void
    {
        $user = $this->actingAsSubscriber(['password' => 'Geheim-wachtwoord-1', 'email_verified_at' => now()]);

        $this->putJson('/api/v1/profile', ['email' => 'aanvaller@example.com'])->assertUnprocessable();
        $this->putJson('/api/v1/profile', ['email' => 'nieuw@example.com', 'current_password' => 'Geheim-wachtwoord-1'])->assertOk();

        $this->assertSame('nieuw@example.com', $user->fresh()->email);
        $this->assertNull($user->fresh()->email_verified_at, 'a new address has to be verified again');
    }

    public function test_feeds_cannot_inject_script_links(): void
    {
        $incident = app(ExternalIncidentIngestor::class)->p2000([
            'id' => 'x1', 'title' => 'Brand', 'description' => 'Brand', 'latitude' => 52.37, 'longitude' => 4.89,
            'category' => 'brand', 'subtype' => 'Brand', 'severity' => 4, 'source_feed' => 'zwaailicht', 'link' => 'javascript:alert(document.cookie)',
        ]);

        $this->assertNull($incident->source_url);
    }
}
