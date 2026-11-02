<?php

namespace Tests\Feature;

use App\Models\Installation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TestPushTest extends TestCase
{
    use RefreshDatabase;

    private function fakeFcm(int $status = 200, array $body = ['name' => 'projects/p/messages/1']): void
    {
        config([
            'services.push.driver' => 'fcm',
            'services.push.fcm_service_account_json' => base64_encode(json_encode(['project_id' => 'veiligerbuiten', 'client_email' => 'x@y', 'private_key' => 'k', 'token_uri' => 'https://oauth2.googleapis.com/token'])),
        ]);
        \Illuminate\Support\Facades\Cache::put('veiligbuiten:fcm-access-token', 'token', 60);
        Http::fake(['fcm.googleapis.com/*' => Http::response($body, $status)]);
    }

    public function test_a_test_push_reaches_every_device_of_the_account(): void
    {
        $this->fakeFcm();
        $user = User::factory()->create(['email' => 'appdemo@mail.com']);
        Installation::create(['token' => str_repeat('a', 64), 'user_id' => $user->id, 'push_platform' => 'android', 'push_token' => 'fcm-token-1', 'last_seen_at' => now()]);
        Installation::create(['token' => str_repeat('b', 64), 'user_id' => $user->id, 'last_seen_at' => now()->subDay()]);

        $this->artisan('veiligbuiten:test-push', ['email' => 'AppDemo@mail.com'])
            ->expectsOutputToContain('Firebase heeft het bericht aangenomen')
            ->expectsOutputToContain('geen push-token')
            ->assertSuccessful();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'messages:send') && $request['message']['token'] === 'fcm-token-1' && $request['message']['data']['incident_id'] === 'test');
    }

    public function test_a_rejected_token_shows_why_and_an_unknown_email_suggests_matches(): void
    {
        $this->fakeFcm(404, ['error' => ['status' => 'NOT_FOUND', 'message' => 'Requested entity was not found.']]);
        $user = User::factory()->create(['email' => 'appdemo@mail.com']);
        Installation::create(['token' => str_repeat('c', 64), 'user_id' => $user->id, 'push_platform' => 'android', 'push_token' => 'stale', 'last_seen_at' => now()]);

        $this->artisan('veiligbuiten:test-push', ['email' => 'appdemo@mail.com'])->expectsOutputToContain('HTTP 404')->assertFailed();
        $this->artisan('veiligbuiten:test-push', ['email' => 'appdemo@mail.nl'])->expectsOutputToContain('Bedoel je: appdemo@mail.com')->assertFailed();
    }
}
