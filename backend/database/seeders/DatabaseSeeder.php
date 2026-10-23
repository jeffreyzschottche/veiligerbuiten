<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Incident;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        Incident::query()->firstOrCreate(
            ['category' => 'brand', 'subtype' => 'Brandweer aanwezig', 'latitude' => 52.3736, 'longitude' => 4.9001],
            ['description' => 'Rookontwikkeling gemeld bij een bedrijfspand. Hulpdiensten zijn ter plaatse.', 'status' => 'officieel', 'source' => 'official', 'confirmations_count' => 14, 'last_confirmed_at' => now(), 'expires_at' => now()->addHours(4)]
        );
        Incident::query()->firstOrCreate(
            ['category' => 'blokkade', 'subtype' => 'Weg deels geblokkeerd', 'latitude' => 52.3659, 'longitude' => 4.8794],
            ['description' => 'Afzetting op de rijbaan. Verkeer wordt omgeleid.', 'status' => 'bevestigd', 'source' => 'user', 'confirmations_count' => 6, 'last_confirmed_at' => now(), 'expires_at' => now()->addHours(3)]
        );
    }
}
