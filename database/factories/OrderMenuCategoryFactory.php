<?php

namespace Database\Factories;

use App\Models\OrderMenuCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderMenuCategoryFactory extends Factory
{
    protected $model = OrderMenuCategory::class;

    public function definition(): array
    {
        return [
            // Spits out a random unique category name with a number to prevent duplicates
            'name' => $this->faker->unique()->word() . ' Video Collection ' . $this->faker->randomNumber(4),
        ];
    }
}