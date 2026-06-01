<?php

namespace Database\Seeders;

use App\Models\Venue;
use App\Models\Country;
use Illuminate\Database\Seeder;

class VenueSeeder extends Seeder
{
    public function run(): void
    {
        $usCountry = Country::where('code', 'US')->first() ?: Country::create([
            'name'          => 'United States', 
            'code'          => 'US', 
            'currency_code' => 'USD', 
            'dial_code'     => '+1'
        ]);

        $ukCountry = Country::where('code', 'GB')->first() ?: Country::create([
            'name'          => 'United Kingdom', 
            'code'          => 'GB', 
            'currency_code' => 'GBP', 
            'dial_code'     => '+44'
        ]);

        // 1. Seed iconic master venues with explicit relational country mapping structures
        $iconicVenues = [
            ['name' => 'Madison Square Garden', 'city' => 'New York', 'state' => 'NY', 'capacity' => 19500, 'country_id' => $usCountry->id],
            ['name' => 'Red Rocks Amphitheatre', 'city' => 'Morrison', 'state' => 'CO', 'capacity' => 9525, 'country_id' => $usCountry->id],
            ['name' => 'The O2 Arena', 'city' => 'London', 'state' => null, 'capacity' => 20000, 'country_id' => $ukCountry->id],
            ['name' => 'Hollywood Bowl', 'city' => 'Los Angeles', 'state' => 'CA', 'capacity' => 17500, 'country_id' => $usCountry->id],
            ['name' => 'Wembley Stadium', 'city' => 'London', 'state' => null, 'capacity' => 90000, 'country_id' => $ukCountry->id],
        ];

        foreach ($iconicVenues as $venue) {
            Venue::firstOrCreate(['name' => $venue['name']], $venue);
        }

        // 2. Sprinkle in 10 completely random factory venues
        Venue::factory(10)->create();
    }
}