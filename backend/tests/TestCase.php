<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Tests praten nooit met echte bronnen; elk verzoek dat niet is nagebootst faalt meteen.
        Http::preventStrayRequests();
    }

    /** Een ingelogde gebruiker met een actief abonnement. */
    protected function actingAsSubscriber(array $attributes = []): User
    {
        $user = User::factory()->create(['subscription_status' => 'active', 'subscription_expires_at' => now()->addMonth(), ...$attributes]);
        Sanctum::actingAs($user);

        return $user;
    }
}
