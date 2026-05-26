<?php
namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderMenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;

class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $menuItem = OrderMenuItem::inRandomOrder()->first() ?? OrderMenuItem::factory()->create();
        
        // 1. Core Lifecycle Status Pool
        $statuses = ['new order', 'in progress', 'client review', 'complete', 'canceled'];
        $assignedStatus = Arr::random($statuses);

        // 2. Conditional Blocker Logic
        $awaiting = [];
        if (in_array($assignedStatus, ['new order', 'in progress'])) {
            // 40% chance this item is actually waiting on external content assets
            if ($this->faker->boolean(40)) {
                $possibleBlockers = ['Voice Over', 'Audio', 'Art'];
                // Randomly assign one or more blockers to the list
                shuffle($possibleBlockers);
                $awaiting = array_slice($possibleBlockers, 0, rand(1, 3));
            }
        }

        // 3. Dynamic Specifications Engine (with 20% user string input override)
        $randomSpecs = [];
        if (!empty($menuItem->form_blueprint)) {
            foreach ($menuItem->form_blueprint as $key => $optionsArray) {
                $singularKey = rtrim($key, 's'); 
                
                if (is_array($optionsArray) && count($optionsArray) > 0) {
                    if ($this->faker->boolean(20)) {
                        $randomSpecs[$singularKey] = 'Custom ' . $this->faker->word() . ' ' . strtoupper($this->faker->lexify('???-##'));
                    } else {
                        $randomSpecs[$singularKey] = Arr::random($optionsArray);
                    }
                }
            }
        }

        return [
            'order_id'           => Order::inRandomOrder()->first()?->id ?? Order::factory(),
            'order_menu_item_id' => $menuItem->id,
            'price_locked'       => $menuItem->default_price,
            'status'             => $assignedStatus,
            'due_date'           => now()->addWeeks(2)->format('Y-m-d'), // Locked exactly 2 weeks out
            'specifications'     => $randomSpecs,
        ];
    }
}