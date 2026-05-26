<?php

namespace Database\Factories;

use App\Models\OrderMenuCategory;
use App\Models\OrderMenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderMenuItemFactory extends Factory
{
    protected $model = OrderMenuItem::class;

    public function definition(): array
    {
        return [
            'order_menu_category_id' => OrderMenuCategory::factory(),
            'name'                   => $this->faker->word(),
            'default_price'          => $this->faker->randomFloat(2, 100, 1500),
            'form_blueprint'         => null,
        ];
    }
}