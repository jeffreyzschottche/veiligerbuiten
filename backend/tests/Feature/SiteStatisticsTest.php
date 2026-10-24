<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteStatisticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['veiligbuiten.site_stats_token' => 'test-server-only-secret']);
    }

    public function test_endpoint_requires_the_server_secret_and_fails_closed_without_configuration(): void
    {
        $this->getJson('/api/v1/internal/site-statistics')->assertNotFound();
        $this->getJson('/api/v1/internal/site-statistics', ['Authorization' => 'Bearer wrong'])->assertNotFound();
        $this->getJson('/api/v1/internal/site-statistics?token=test-server-only-secret')->assertNotFound();
        config(['veiligbuiten.site_stats_token' => '']);
        $this->getJson('/api/v1/internal/site-statistics', $this->headers())->assertServiceUnavailable();
    }

    public function test_snapshot_contains_only_the_total_and_timestamp_and_is_stable_for_the_day(): void
    {
        User::factory()->count(3)->create();
        $first = $this->getJson('/api/v1/internal/site-statistics', $this->headers())
            ->assertOk()->assertJsonPath('total', 979)->assertJsonCount(2)->json();
        $this->assertNotEmpty($first['updated_at']);
        User::factory()->create();
        $this->getJson('/api/v1/internal/site-statistics', $this->headers())->assertExactJson($first);
        $this->assertDatabaseHas('cache', ['key' => config('cache.prefix').'site:community:snapshot:v1']);
    }

    public function test_next_amsterdam_calendar_day_refreshes_the_count_even_without_the_scheduler(): void
    {
        $this->travelTo(now('Europe/Amsterdam')->startOfDay()->addHours(23));
        User::factory()->count(2)->create();
        $this->getJson('/api/v1/internal/site-statistics', $this->headers())->assertJsonPath('total', 978);
        User::factory()->create();
        $this->travel(2)->hours();
        $this->getJson('/api/v1/internal/site-statistics', $this->headers())->assertJsonPath('total', 979);
    }

    public function test_publish_command_refreshes_existing_snapshot(): void
    {
        $this->getJson('/api/v1/internal/site-statistics', $this->headers())->assertJsonPath('total', 976);
        User::factory()->count(4)->create();
        $this->artisan('veiligbuiten:publish-site-statistics')->assertSuccessful();
        $this->getJson('/api/v1/internal/site-statistics', $this->headers())->assertJsonPath('total', 980);
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer test-server-only-secret'];
    }
}
