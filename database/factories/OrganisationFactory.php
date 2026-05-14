<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Organisation>
 */
class OrganisationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'billing_address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->stateAbbr(),
            'zip' => fake()->postcode(),
            // Since you seeded exactly 50 countries, we can pick a random ID between 1 and 50
            'country_id' => fake()->numberBetween(1, 50),
            'credit_limit' => fake()->randomFloat(2, 5000, 100000), // $5k to $100k
            'credit_terms' => fake()->randomElement(['Net 15', 'Net 30', 'Net 60', 'Due on Receipt']),
            'currency_id' => fake()->numberBetween(1, 50),
            
            'accounts_payable_contact' => fake()->name(),
            // Generate an array of 2 random emails for the AP department
            'accounts_payable_emails' => [
                fake()->safeEmail(),
                fake()->safeEmail()
            ],
        ];
    }
}