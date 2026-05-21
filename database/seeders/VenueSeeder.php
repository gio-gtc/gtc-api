<?php

namespace Database\Seeders;

use App\Models\Venue;
use Illuminate\Database\Seeder;

class VenueSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed iconic master venues for deterministic testing
        $iconicVenues = [
            ['name' => 'Madison Square Garden', 'city' => 'New York', 'state' => 'NY', 'capacity' => 19500],
            ['name' => 'Red Rocks Amphitheatre', 'city' => 'Morrison', 'state' => 'CO', 'capacity' => 9525],
            ['name' => 'The O2 Arena', 'city' => 'London', 'state' => null, 'capacity' => 20000],
            ['name' => 'Hollywood Bowl', 'city' => 'Los Angeles', 'state' => 'CA', 'capacity' => 17500],
            ['name' => 'Wembley Stadium', 'city' => 'London', 'state' => null, 'capacity' => 90000],
        ];

        foreach ($iconicVenues as $venue) {
            Venue::firstOrCreate(['name' => $venue['name']], $venue);
        }

        // 2. Sprinkle in 10 completely random factory venues
        Venue::factory(10)->create();
    }
}