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
        // Pulls a real status record from the dictionary table to preserve string checks
        $statusRecord = OrderItemStatus::inRandomOrder()->first() 
            ?? OrderItemStatus::create(['name' => 'Unassigned']);
        
        $assignedStatusName = $statusRecord->name;

        // 2. Conditional Blocker Logic (Evaluates smoothly via the lookup table record name)
        $awaiting = [];
        if (in_array($assignedStatusName, ['Still In Cart', 'Unassigned', 'In Production'])) {
            // 40% chance this item is actually waiting on external content assets
            if ($this->faker->boolean(40)) {
                $possibleBlockers = ['Voice Over', 'Audio', 'Art'];
                shuffle($possibleBlockers);
                $awaiting = array_slice($possibleBlockers, 0, rand(1, 3));
            }
        }

        // 3. Dynamic Specifications Engine (Preserved exactly)
        $randomSpecs = [];
        if (!empty($awaiting)) {
            $randomSpecs['awaiting_assets'] = $awaiting;
        }

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
        // Maps to the relational foreign key integer index rather than raw text strings
        $finalStatusId = $order->is_demo ? null : $statusRecord->id;
        $finalDueDate = $order->is_demo ? null : now()->addWeeks(2)->format('Y-m-d');

        return [
            'order_id'             => $order->id,
            'order_menu_item_id'   => $menuItem->id,
            'locked_price'         => $menuItem->default_price,
            'order_item_status_id' => $finalStatusId,
            'due_date'             => $finalDueDate,
            'specifications'       => $randomSpecs,
        ];
    }
}