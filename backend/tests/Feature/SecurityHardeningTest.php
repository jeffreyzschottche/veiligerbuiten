<?php

namespace Tests\Feature;

use App\Models\Installation;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/** Review van 7 november 2026; zie docs/beveiliging.md. */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function login(string $email, array $server = []): \Illuminate\Testing\TestResponse
    {
        return $this->withServerVariables($server)->postJson('/api/v1/login', ['email' => $email, 'password' => 'fout']);
    }

    public function test_every_route_has_its_own_rate_limit_counter(): void
    {
        // Vroeger telden alle anonieme verzoeken samen: na tien keer de categorieën ophalen kon niemand meer inloggen.
        foreach (range(1, 12) as $i) {
            $this->getJson('/api/v1/categories')->assertOk();
        }
        $this->login('iemand@example.com')->assertUnprocessable();
    }

    public function test_the_real_client_ip_behind_the_proxy_is_used_for_rate_limits(): void
    {
        // Achter Traefik (privé-IP) telt het IP van de bezoeker, niet dat van de proxy.
        foreach (range(1, 12) as $i) {
            $this->login("persoon{$i}@example.com", ['REMOTE_ADDR' => '10.0.1.5', 'HTTP_X_FORWARDED_FOR' => "203.0.113.{$i}"])->assertUnprocessable();
        }
    }

    public function test_a_client_on_a_public_ip_cannot_spoof_its_address(): void
    {
        foreach (range(1, 10) as $i) {
            $this->login("persoon{$i}@example.com", ['REMOTE_ADDR' => '198.51.100.7', 'HTTP_X_FORWARDED_FOR' => "203.0.113.{$i}"])->assertUnprocessable();
        }
        $this->login('persoon11@example.com', ['REMOTE_ADDR' => '198.51.100.7', 'HTTP_X_FORWARDED_FOR' => '203.0.113.11'])->assertStatus(429);
    }

    public function test_fake_webhooks_do_not_turn_the_ops_status_red(): void
    {
        config(['services.ops.token' => 'geheim', 'services.stripe.webhook_secret' => 'whsec_test', 'services.revenuecat.webhook_secret' => 'rc_test']);
        foreach (range(1, 25) as $i) {
            $this->postJson('/api/v1/webhooks/stripe', ['type' => 'invoice.paid'], ['Stripe-Signature' => 't='.time().',v1=nep'])->assertStatus(400);
            $this->postJson('/api/v1/webhooks/revenuecat', ['event' => ['type' => 'RENEWAL']], ['Authorization' => 'Bearer nep'])->assertUnauthorized();
        }

        $checks = collect($this->getJson('/api/v1/ops/status', ['Authorization' => 'Bearer geheim'])->assertOk()->json('checks'))->keyBy('key');
        $this->assertSame('ok', $checks['errors']['status']);
    }

    public function test_mcp_rejects_huge_batches_and_odd_arguments(): void
    {
        $user = User::factory()->create(['subscription_status' => 'active', 'subscription_expires_at' => now()->addMonth()]);
        $token = $user->rotateAiToken();
        $call = fn (int $id, array $arguments) => ['jsonrpc' => '2.0', 'id' => $id, 'method' => 'tools/call', 'params' => ['name' => 'get_situations_nearby', 'arguments' => $arguments]];

        $this->postJson("/mcp/{$token}", array_map(fn ($i) => $call($i, ['latitude' => 52.37, 'longitude' => 4.89]), range(1, 11)))->assertStatus(400);
        $this->postJson("/mcp/{$token}", array_map(fn ($i) => $call($i, ['latitude' => 52.37, 'longitude' => 4.89]), range(1, 10)))->assertOk()->assertJsonCount(10);

        $this->postJson("/mcp/{$token}", $call(1, ['place' => ['x'], 'min_level' => ['danger']]))->assertOk()->assertJsonPath('result.isError', true);
        $this->postJson("/mcp/{$token}", ['jsonrpc' => '2.0', 'id' => 2, 'method' => ['tools/list'], 'params' => 'x'])->assertOk()->assertJsonPath('error.code', -32601);
    }

    public function test_only_the_owner_can_link_or_unlink_a_device(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $installation = Installation::create(['token' => str_repeat('a', 64), 'user_id' => $owner->id, 'trust_score' => 50]);
        $headers = function (User $user) use ($installation) {
            $this->app['auth']->forgetGuards(); // de testclient onthoudt anders de vorige gebruiker

            return ['X-Installation-Token' => $installation->token, 'Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken];
        };

        $this->putJson('/api/v1/installations/link', ['unlink' => true], $headers($other))->assertForbidden();
        $this->putJson('/api/v1/installations/link', [], $headers($other))->assertForbidden();
        $this->putJson('/api/v1/installations/push-token', ['push_token' => str_repeat('p', 30), 'platform' => 'ios'], $headers($other))->assertOk();
        $this->assertSame($owner->id, $installation->fresh()->user_id);

        $this->putJson('/api/v1/installations/link', ['unlink' => true], $headers($owner))->assertOk();
        $this->assertNull($installation->fresh()->user_id);
        $this->putJson('/api/v1/installations/link', [], $headers($other))->assertOk();
        $this->assertSame($other->id, $installation->fresh()->user_id);
    }

    public function test_logging_out_unlinks_this_device(): void
    {
        $user = User::factory()->create();
        $installation = Installation::create(['token' => str_repeat('b', 64), 'user_id' => $user->id, 'trust_score' => 50]);

        $this->postJson('/api/v1/logout', [], ['X-Installation-Token' => $installation->token, 'Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken])->assertOk();

        $this->assertNull($installation->fresh()->user_id);
    }

    public function test_login_tokens_expire_after_a_long_time_without_use(): void
    {
        $user = User::factory()->create();
        $active = $user->createToken('actief');
        $idle = $user->createToken('vergeten');
        PersonalAccessToken::query()->whereKey($idle->accessToken->id)->update(['last_used_at' => now()->subDays(91), 'created_at' => now()->subDays(200)]);
        PersonalAccessToken::query()->whereKey($active->accessToken->id)->update(['last_used_at' => now()->subDays(80), 'created_at' => now()->subDays(400)]);

        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$idle->plainTextToken])->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$active->plainTextToken])->assertOk();

        $prune = collect($this->app->make(Schedule::class)->events())->firstWhere('description', 'prune-idle-tokens');
        $prune->run($this->app);
        $this->assertSame([$active->accessToken->id], PersonalAccessToken::query()->pluck('id')->all());
    }
}
