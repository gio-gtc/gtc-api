<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemStatus;
use App\Models\OrderShowDate;
use App\Models\OrderMenuItem;
use App\Models\OrderItemBroadcastSpecification;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MockOrderSeeder extends Seeder
{
    public function run(): void
    {
        $isciSequence = 1;

        OrderItem::query()->delete();
        OrderShowDate::query()->delete();
        Order::query()->delete();
        Tour::query()->delete();

        $dummyUsers = User::factory(25)->create();
        $dummyUsers->each(function ($user) {
            $randomRole = fake()->randomElement(['Designer', 'Client']);            
            $user->assignRole($randomRole);
        });

        Tour::factory(5)->create();
        $tours = Tour::all();
        $tourCount = $tours->count();

        if ($tourCount === 0) {
            $tours = Tour::factory(4)->create();
            $tourCount = $tours->count();
        }

        $broadcastMenuItem = OrderMenuItem::where('order_menu_category_id', 1)->first() ?? OrderMenuItem::create([
            'order_menu_category_id' => 1,
            'name'                   => 'Broadcast & Streaming Video Details',
            'slug'                   => 'broadcast-streaming-video-details',
            'default_price'          => 250.00,
            'tags'                   => ['Audio', 'Voice Over'], // Ensure tags are defined
            'form_blueprint'         => [
                'encodings' => ['Station MP4 (Broadcast)', 'Connect TV', 'ProRes 422 HQ'],
                'types' => [
                    'Generic' => [
                        'cuts' => ['Pre Sale', 'Week of', 'Post Sale'],
                        'durations' => [15, 30, 60],
                        'languages' => ['English', 'Spanish', 'French']
                    ],
                    'International' => [
                        'cuts' => ['International TV Package'],
                        'durations' => [30],
                        'languages' => ['English']
                    ]
                ]
            ]
        ]);

        $itemStatuses = OrderItemStatus::all();
        $cancelledStatus = $itemStatuses->whereIn('name', ['Cancelled', 'Cancelled'])->first();
        $cancelledId = $cancelledStatus ? $cancelledStatus->id : 5;

        $statusPool = array_merge(
            array_fill(0, 3, $cancelledId),
            array_fill(0, 7, $itemStatuses->where('name', 'Still In Cart')->first()?->id ?? 1),
            array_fill(0, 7, $itemStatuses->where('name', 'Unassigned')->first()?->id ?? 2),
            array_fill(0, 8, $itemStatuses->where('name', 'In Production')->first()?->id ?? 3),
            array_fill(0, 7, $itemStatuses->where('name', 'Client Review')->first()?->id ?? 4),
            array_fill(0, 8, $itemStatuses->where('name', 'Out For Delivery')->first()?->id ?? 6)
        );
        shuffle($statusPool);

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

            $orderItems = rand(4, 10);
            for ($k = 0; $k < $orderItems; $k++) {
                $revisionNumber = rand(0, 2);
                $paddedNumber = str_pad($isciSequence, 6, '0', STR_PAD_LEFT);
                $baseIsci = "GTC{$paddedNumber}";
                
                $isciSequence++;

                $finalIsci = $revisionNumber > 0 ? "{$baseIsci}R{$revisionNumber}" : $baseIsci;

                $allocatedStatusId = array_pop($statusPool) ?? $itemStatuses->where('name', 'Unassigned')->first()?->id ?? 2;
                $mediaType = fake()->randomElement(['Generic', 'International']);
                
                if ($mediaType === 'International') {
                    $cut = 'International TV Package';
                    $duration = 30;
                    $language = 'English';
                } else {
                    $cut = fake()->randomElement(['Pre Sale', 'Week of', 'Post Sale']);
                    $duration = fake()->randomElement([15, 30, 60]);
                    $language = fake()->randomElement(['English', 'Spanish', 'French']);
                }

                // A. Persist the updated child row data block using the clean new class structure
                $broadcastSpec = OrderItemBroadcastSpecification::create([
                    'type'             => $mediaType,
                    'cut'              => $cut,
                    'duration_seconds' => $duration,
                    'language'         => $language,
                    'encoding'         => fake()->randomElement(['Station MP4 (Broadcast)', 'Connect TV', 'ProRes 422 HQ']),
                    'encoding_custom'  => null,
                    'isci'             => $finalIsci,
                ]);

                // B. Save matching record to master index log mapping line
                OrderItem::create([
                    'order_id'             => $order->id,
                    'order_menu_item_id'   => $broadcastMenuItem->id,
                    'order_item_status_id' => $allocatedStatusId,
                    'locked_price'         => $broadcastMenuItem->default_price ?? 250.00,
                    'due_date'             => now()->addDays(rand(5, 25))->format('Y-m-d'),
                    'specifiable_id'       => $broadcastSpec->id,
                    'specifiable_type'     => OrderItemBroadcastSpecification::class,
                    'revision_number'      => $revisionNumber,
                    'audio_received'       => false,
                    'voice_over_received'  => false,
                    'art_received'         => null,
                ]);
            }
        }

        $this->call([
            OrderItemAssigneeSeeder::class,
        ]);
    }
}