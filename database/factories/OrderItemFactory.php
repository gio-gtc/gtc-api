<?php
namespace Database\Factories;

use App\Models\OrderItem;
use App\Models\Order;
use App\Models\OrderMenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;

class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        // 🚀 FIX: Establish the order right away so we can safely check if it's a demo
        $order = Order::inRandomOrder()->first() ?? Order::factory()->create();
        $menuItem = OrderMenuItem::inRandomOrder()->first() ?? OrderMenuItem::factory()->create();
        
        // 1. Core Lifecycle Status Pool (Updated to your real pipeline)
        $statuses = ['Still In Cart', 'Unassigned', 'In Production', 'Client Review', 'Out For Delivery', 'Canceled'];
        $assignedStatus = Arr::random($statuses);

        // 2. Conditional Blocker Logic (Mapped to your new active production states)
        $awaiting = [];
        if (in_array($assignedStatus, ['Still In Cart', 'Unassigned', 'In Production'])) {
            // 40% chance this item is actually waiting on external content assets
            if ($this->faker->boolean(40)) {
                $possibleBlockers = ['Voice Over', 'Audio', 'Art'];
                shuffle($possibleBlockers);
                $awaiting = array_slice($possibleBlockers, 0, rand(1, 3));
            }
        }

        // 3. Dynamic Specifications Engine (Preserved exactly)
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

        $finalStatus = $order->is_demo ? null : $assignedStatus;
        $finalDueDate = $order->is_demo ? null : now()->addWeeks(2)->format('Y-m-d');

        return [
            'order_id'           => $order->id,
            'order_menu_item_id' => $menuItem->id,
            'price_locked'       => $menuItem->default_price,
            'status'             => $finalStatus,
            'due_date'           => $finalDueDate,
            'specifications'     => $randomSpecs,
        ];
    }
}