<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemStatus;
use App\Models\OrderShowDate;
use App\Models\OrderMenuItem;
use App\Models\OrderItemBroadcastSpecs;
use App\Models\OrderItemRadioSpecs;
use App\Models\OrderItemSocialSpecs;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Exception;

class MockOrderSeeder extends Seeder
{
    public function run(): void
    {
        $isciSequence = 1;
        $encodingsPool = ['H264-MP4 (Online or Venue)', 'Station MP4 (Broadcast)', 'Hulu', 'Amazon', 'Netflix', 'Connect TV', 'Custom Encode TEst'];

        OrderItem::query()->delete();
        OrderShowDate::query()->delete();
        Order::query()->delete();
        Tour::query()->delete();

        $dummyUsers = User::factory(25)->create();
        $dummyUsers->each(function ($user) {
            $randomRole = fake()->randomElement(['Designer', 'Client']);            
            $user->assignRole($randomRole);
        });

        // 1. Gather the designer pool right after they are created
        $designers = User::role('Designer')->get();

        Tour::factory(5)->create();
        $tours = Tour::all();
        $tourCount = $tours->count();

        if ($tourCount === 0) {
            $tours = Tour::factory(4)->create();
            $tourCount = $tours->count();
        }

        // 2. Define Menu Items
        $broadcastMenuItem = OrderMenuItem::where('order_menu_category_id', 1)->first();
        $socialMenuItem = OrderMenuItem::where('order_menu_category_id', 2)->first();
        $radioMenuItem = OrderMenuItem::where('order_menu_category_id', 3)->first();

        // 3. Extract Statuses and hold specific IDs for logic checks
        $itemStatuses = OrderItemStatus::all();
        $cancelledStatus = $itemStatuses->where('name', 'Cancelled')->first();
        $cancelledId = $cancelledStatus ? $cancelledStatus->id : 5;

        $stillInCartId = $itemStatuses->where('name', 'Still In Cart')->first()?->id ?? 1;
        $unassignedId = $itemStatuses->where('name', 'Unassigned')->first()?->id ?? 2;

        $statusPool = array_merge(
            array_fill(0, 3, $cancelledId),
            array_fill(0, 7, $stillInCartId),
            array_fill(0, 7, $unassignedId),
            array_fill(0, 8, $itemStatuses->where('name', 'In Production')->first()?->id ?? 3),
            array_fill(0, 7, $itemStatuses->where('name', 'Client Review')->first()?->id ?? 4),
            array_fill(0, 7, $itemStatuses->where('name', 'Revision Request')->first()?->id ?? 5),
            array_fill(0, 8, $itemStatuses->where('name', 'Out For Delivery')->first()?->id ?? 6)
        );
        shuffle($statusPool);

        // ORDER COUNT
        for ($i = 0; $i < 11; $i++) {
            $assignedTour = $tours[$i % $tourCount];

            $order = Order::factory()->create([
                'tour_id' => $assignedTour->id,
            ]);

            $runNights = rand(1, 3);
            for ($j = 0; $j < $runNights; $j++) {
                OrderShowDate::create([
                    'order_id'  => $order->id,
                    'show_date' => now()->addDays(rand(1, 30))->format('Y-m-d'),
                ]);
            }

            // ORDER ITEMS COUNT
            $orderItems = rand(4, 10);
            $orderItemTypes = ['Social Video', 'Broadcast', 'Radio'];

            $Manifest = array_map(fn() => Arr::random($orderItemTypes), range(1, $orderItems));

            foreach ($Manifest as $itemType) {
                $revisionNumber = rand(0, 2);
                $paddedNumber = str_pad($isciSequence++, 6, '0', STR_PAD_LEFT);
                $finalIsci = $revisionNumber > 0 ? "GTC{$paddedNumber}R{$revisionNumber}" : "GTC{$paddedNumber}";
                
                $allocatedStatusId = array_pop($statusPool) ?? $unassignedId;
                $menuItem = match ($itemType) {
                    'Broadcast'    => $broadcastMenuItem,
                    'Social Video' => $socialMenuItem,
                    'Radio'        => $radioMenuItem,
                };

                // Define the 'Recipe' for each type
                [$modelClass, $specData] = match ($itemType) {
                    'Broadcast' => [
                        OrderItemBroadcastSpecs::class,
                        (function() use ($encodingsPool, $finalIsci) {
                            $type = fake()->randomElement(['Generic', 'Amex', 'Citi', 'Verison', 'International']);
                            return [
                                'type'             => $type,
                                'cut'              => ($type === 'International') ? 'International TV Package' : fake()->randomElement(['Sign Up Now', 'Pre Sale', 'On Sale Now', 'Week of', 'Day Prior', 'Day Of', 'Superless', 'Sample']),
                                'duration_seconds' => ($type === 'International') ? 30 : fake()->randomElement([10, 15, 30]),
                                'language'         => ($type === 'International') ? 'English' : fake()->randomElement(['English', 'Spanish', 'French']),
                                'encoding'         => array_slice(Arr::shuffle($encodingsPool), 0, rand(1, 2)),
                                'isci'             => $finalIsci,
                            ];
                        })(),
                    ],
                    'Social Video' => [
                        OrderItemSocialSpecs::class,
                        [
                            'type'             => fake()->randomElement(['Social - 16:9', 'FB/IG Story', 'TikTok', 'Social Square', 'Social - 4:5']),
                            'cut'              => fake()->randomElement(['Pre Sale', 'On Sale Now', "Evergreen", 'Sign Up Now']),
                            'card_holder'      => fake()->randomElement(['Amex', 'Citi']),
                            'duration_seconds' => (string)fake()->randomElement([10, 15, 30]),
                            'language'         => fake()->randomElement(['English', 'Spanish', 'French']),
                            'isci'             => $finalIsci,
                        ]
                    ],
                    'Radio' => [
                        OrderItemRadioSpecs::class,
                        (function() use ($finalIsci) {
                            $type = fake()->randomElement(['Generic', 'Amex', 'Citi', 'Verison', 'International']);
                            return[
                                'type'             => $type,
                                'cut'              => ($type === 'International') ? 'International Radio Package' : fake()->randomElement(['Sign Up Now', 'Pre Sale', 'On Sale Now', 'Week of', 'Day Prior', 'Day Of']),
                                'duration_seconds' => ($type === 'International') ? 30 : (string)fake()->randomElement([15, 30, 60]),
                                'language'         => ($type === 'International') ? 'English' : fake()->randomElement(['English', 'Spanish', 'French']),
                                'isci'             => $finalIsci,
                            ];
                        }) ()
                    ],
                    default => throw new Exception("Unknown item type: {$itemType}"),
                };

                // Create the spec and link to order item
                $spec = $modelClass::create($specData);

                // Dynamically sets order_menu_item_id using $menuItem verified above
                $orderItem = OrderItem::create([
                    'order_id'             => $order->id,
                    'order_menu_item_id'   => $menuItem->id,
                    'order_item_status_id' => $allocatedStatusId,
                    'locked_price'         => $menuItem->default_price,
                    'due_date'             => now()->addDays(rand(5, 25))->format('Y-m-d'),
                    'specifiable_id'       => $spec->id,
                    'specifiable_type'     => $modelClass,
                    'revision_number'      => $revisionNumber,
                ]);

                // 4. Assignee Guard Logic
                // If the item status is NOT "Still In Cart" or "Unassigned", attach designers
                if ($allocatedStatusId !== $stillInCartId && $allocatedStatusId !== $unassignedId && $designers->isNotEmpty()) {
                    $randomDesigners = $designers->random(rand(1, 2))->pluck('id');
                    
                    // Uses attach() to append assignments to the item's pivot table
                    $orderItem->assignees()->attach($randomDesigners);
                }
            }
        }
        
        // OrderItemAssigneeSeeder call removed since assignments are handled perfectly inline above!
    }
}