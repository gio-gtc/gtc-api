<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemStatus;
use App\Models\OrderShowDate;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Database\Seeder;

class MockOrderSeeder extends Seeder
{
    public function run(): void
    {
        OrderItem::truncate();
        OrderShowDate::truncate();
        Order::truncate();
        Tour::truncate();

        // 1. Generate random dummy testing users
        $dummyUsers = User::factory(25)->create();
        $dummyUsers->each(function ($user) {
            $randomRole = fake()->randomElement(['Designer', 'Client']);            
            $user->assignRole($randomRole);
        });

        // 2. Generate mock tours cluster
        Tour::factory(5)->create();
        $tours = Tour::all();
        $tourCount = $tours->count();

        if ($tourCount === 0) {
            $tours = Tour::factory(4)->create();
            $tourCount = $tours->count();
        }

        // 3. Build an absolute pool of 40 elements to guarantee status allocations
        $itemStatuses = OrderItemStatus::all();
        $statusPool = array_merge(
            array_fill(0, 3, $itemStatuses->where('name', 'Canceled')->first()->id),
            array_fill(0, 7, $itemStatuses->where('name', 'Still In Cart')->first()->id),
            array_fill(0, 7, $itemStatuses->where('name', 'Unassigned')->first()->id),
            array_fill(0, 8, $itemStatuses->where('name', 'In Production')->first()->id),
            array_fill(0, 7, $itemStatuses->where('name', 'Client Review')->first()->id),
            array_fill(0, 8, $itemStatuses->where('name', 'Out For Delivery')->first()->id)
        );
        shuffle($statusPool);

        // 4. Generate the 20 test orders sequentially via a round-robin loop
        for ($i = 0; $i < 20; $i++) {
            $assignedTour = $tours[$i % $tourCount];

            $order = Order::factory()->create([
                'tour_id' => $assignedTour->id,
            ]);

            // Automatically attach 1 to 3 random running show nights
            $runNights = rand(1, 3);
            for ($j = 0; $j < $runNights; $j++) {
                OrderShowDate::create([
                    'order_id'  => $order->id,
                    'show_date' => now()->addDays(rand(1, 30))->format('Y-m-d'),
                ]);
            }

            // Create exactly 2 items per order using our balanced dictionary pool ids
            for ($k = 0; $k < 2; $k++) {
                $allocatedStatusId = array_pop($statusPool) ?? $itemStatuses->where('name', 'Unassigned')->first()->id;

                OrderItem::factory()->create([
                    'order_id'             => $order->id,
                    'order_item_status_id' => $allocatedStatusId,
                ]);
            }
        }

        // 5. Run the relational design staff line item assignment mock scripts
        $this->call([
            OrderItemAssigneeSeeder::class,
        ]);
    }
}