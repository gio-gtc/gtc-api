<?php

namespace Database\Factories;

use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

class VenueFactory extends Factory
{
    protected $model = Venue::class;

    public function definition(): array
    {
        $venueSuffixes = ['Arena', 'Stadium', 'Amphitheatre', 'Center', 'Bowl', 'Hall'];

        return [
            'name' => $this->faker->company() . ' ' . $this->faker->randomElement($venueSuffixes),
            'city' => $this->faker->city(),
            'state' => $this->faker->stateAbbr(),
            'capacity' => $this->faker->optional(0.8)->numberBetween(5000, 80000), // 20% chance of being null
        ];
    }
}