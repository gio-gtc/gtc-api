<?php

namespace Database\Factories;

use App\Models\MenuItem;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        $menuItem = MenuItem::inRandomOrder()->first() ?? MenuItem::factory()->create();
        
        // Formulate dynamic JSON contents to test our flexible specifications engine
        $specs = match($menuItem->menu_category_id) {
            1 => ['cuts' => 'First Cut', 'duration' => 30, 'language' => 'English', 'encoding' => 'ProRes 422', 'isci' => 'VID' . $this->faker->randomNumber(5)],
            2 => ['card_holder' => 'Amex', 'duration' => 15, 'language' => 'Spanish'],
            default => ['dimensions' => '1080x1350', 'profile' => 'RGB']
        };

        return [
            'order_id' => Order::inRandomOrder()->first()?->id ?? Order::factory(),
            'menu_item_id' => $menuItem->id,
            'price_locked' => $menuItem->default_price,
            'status' => $this->faker->randomElement(['New', 'In Production', 'Client Review', 'Complete']),
            'due_date' => $this->faker->dateTimeBetween('now', '+2 weeks')->format('Y-m-d'),
            'specifications' => $specs,
        ];
    }
}