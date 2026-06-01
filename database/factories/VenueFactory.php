<?php

namespace Database\Factories;

use App\Models\Venue;
use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

class VenueFactory extends Factory
{
    protected $model = Venue::class;

    public function definition(): array
    {
        $usCountry = Country::where('code', 'US')->first();
        $randomCountry = Country::inRandomOrder()->first();

        $isDomestic = $this->faker->boolean(80);
        
        $assignedCountry = ($isDomestic && $usCountry) ? $usCountry : ($randomCountry ?? Country::firstOrCreate(
            ['code' => 'US'],
            [
                'name'          => 'United States',
                'currency_code' => 'USD',
                'dial_code'     => '+1'
            ]
        ));

        return [
            'name'           => $this->faker->company() . ' Arena',
            'street_address' => $this->faker->streetAddress(),
            'city'           => $this->faker->city(),
            'state'          => $isDomestic ? $this->faker->stateAbbr() : $this->faker->state(),
            'postal_code'    => $this->faker->postcode(),
            'country_id'     => $assignedCountry->id,
        ];
    }
}