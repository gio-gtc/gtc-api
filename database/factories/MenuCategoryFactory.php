<?php

namespace Database\Factories;

use App\Models\MenuCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class MenuCategoryFactory extends Factory
{
    protected $model = MenuCategory::class;

    public function definition(): array
    {
        return [
            // Spits out a random unique category name with a number to prevent duplicates
            'name' => $this->faker->unique()->word() . ' Video Collection ' . $this->faker->randomNumber(4),
        ];
    }
}