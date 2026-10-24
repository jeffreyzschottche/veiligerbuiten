<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\Installation;
use App\Support\DangerScale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HarassmentTest extends TestCase
{
    use RefreshDatabase;

    private function report(Installation $installation, string $subtype, array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/reports', [
            'category' => 'intimidatie', 'subtype' => $subtype, 'latitude' => 52.3702, 'longitude' => 4.8952,
            'user_latitude' => 52.3705, 'user_longitude' => 4.8952, 'location_accuracy_m' => 15, ...$overrides,
        ], ['X-Installation-Token' => $installation->token]);
    }

    public function test_harassment_is_visible_right_away_short_lived_and_weighted_by_type(): void
    {
        $user = $this->actingAsSubscriber();
        $installation = Installation::create(['token' => str_repeat('h', 64), 'trust_score' => 50, 'user_id' => $user->id]);

        $groping = Incident::find($this->report($installation, 'Handtastelijke persoon')->assertCreated()->json('data.id'));
        $catcall = Incident::find($this->report($installation, 'Nafluiten / naroepen', ['latitude' => 52.376])->assertCreated()->json('data.id'));

        $this->assertSame('public', $groping->visibility, 'no waiting for a second witness');
        $this->assertSame(4, $groping->severity);
        $this->assertSame('warn', DangerScale::level($groping), 'one unconfirmed report is never danger');
        $this->assertSame('info', DangerScale::level($catcall));
        $this->assertEqualsWithDelta(60, now()->diffInMinutes($groping->expires_at), 1);
        $this->assertLessThan(500, DangerScale::alertRadius($catcall), 'harassment alerts stay local');
    }

    public function test_other_harassment_needs_a_short_description(): void
    {
        $user = $this->actingAsSubscriber();
        $installation = Installation::create(['token' => str_repeat('i', 64), 'trust_score' => 50, 'user_id' => $user->id]);

        $this->report($installation, 'Anders: intimidatie')->assertUnprocessable();
        $this->report($installation, 'Anders: intimidatie', ['description' => 'Man blijft bij de bushalte staan'])->assertCreated();
    }

    public function test_harassment_can_be_filtered_in_alert_settings(): void
    {
        $this->actingAsSubscriber();

        $this->putJson('/api/v1/me/settings', ['alert_filters' => ['categories' => ['intimidatie']]])->assertOk();
    }

    public function test_police_news_about_indecent_assault_lands_in_harassment(): void
    {
        config(['services.politie.enabled' => true, 'services.politie.nationwide' => true]);
        Http::fake(['api.politie.nl/*' => Http::response(['iterator' => ['last' => true], 'nieuwsberichten' => [[
            'uid' => 'z1', 'titel' => 'Vrouw aangerand in park, politie zoekt getuigen', 'introductie' => 'Een vrouw is gisteravond met geweld aangerand.',
            'url' => 'https://www.politie.nl/nieuws/z1.html', 'publicatiedatum' => now('Europe/Amsterdam')->subHour()->format('Y-m-d H:i:s'),
            'locaties' => [['latitude' => 52.37, 'longitude' => 4.89]],
        ]]])]);

        $this->artisan('veiligbuiten:ingest-police-news')->assertSuccessful();

        $this->assertSame('intimidatie', Incident::sole()->category, 'mentions violence too, but belongs with harassment');
    }
}
