<?php

namespace Database\Factories;

use App\Models\OrderItem;
use App\Models\Order;
use App\Models\OrderMenuItem;
use App\Models\OrderItemStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;

class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        // Establish the order right away so we can safely check if it's a demo
        $order = Order::inRandomOrder()->first() ?? Order::factory()->create();
        $menuItem = OrderMenuItem::inRandomOrder()->first() ?? OrderMenuItem::factory()->create();
        
        // 1. Relational Status Lookup Engine
        $statusRecord = OrderItemStatus::inRandomOrder()->first() 
            ?? OrderItemStatus::create(['name' => 'Unassigned']);
        
        $assignedStatusName = $statusRecord->name;

        // 2. Conditional Blocker Logic
        $awaiting = [];
        if (in_array($assignedStatusName, ['Still In Cart', 'Unassigned', 'In Production'])) {
            if ($this->faker->boolean(40)) {
                $possibleBlockers = ['Audio', 'Art'];
                shuffle($possibleBlockers);
                $awaiting = array_slice($possibleBlockers, 0, rand(1, 3));
            }
        }

        // 3. Dynamic Specifications Engine
        $randomSpecs = [];
        if (!empty($awaiting)) {
            $randomSpecs['awaiting_assets'] = $awaiting;
        }

        // Localization Tracking Strategy
        $isCategory4 = ((int) $menuItem->order_menu_category_id === 4);
        $randomSpecs['is_localized'] = $isCategory4 ? $this->faker->boolean(80) : $this->faker->boolean(5);

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

        // 4. Conditional Demo Shell Values
        $finalStatusId = $order->is_demo ? null : $statusRecord->id;
        $finalDueDate = $order->is_demo ? null : now()->addWeeks(2)->format('Y-m-d');

        return [
            'order_id'             => Order::factory(),
            'order_menu_item_id'   => OrderMenuItem::factory(),
            'order_item_status_id' => 1,
            'locked_price'         => $this->faker->randomElement([150.00, 250.00, 500.00]),
            'due_date'             => $this->faker->dateTimeBetween('+1 week', '+1 month')->format('Y-m-d'),
            'revision_number'      => 0,            
            'specifiable_id'       => null,
            'specifiable_type'     => null,
        ];
    }
}