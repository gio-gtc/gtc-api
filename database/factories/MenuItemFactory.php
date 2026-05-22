<?php

namespace Database\Factories;

use App\Models\MenuCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class MenuItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'menu_category_id' => MenuCategory::inRandomOrder()->first()?->id ?? MenuCategory::factory(),
            'name' => $this->faker->word() . ' Deliverable Asset',
            'default_price' => $this->faker->randomFloat(2, 100, 1500),
        ];
    }
}