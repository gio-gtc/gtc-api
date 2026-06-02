<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Tour;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\OrderStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderDatabaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed our status lookup matrices into the testing environment memory
        $this->seed(OrderStatusSeeder::class);
    }

    /**
     * Verify that a fresh order container can be created and defaults to an unsubmitted state.
     */
    public function test_an_order_can_be_created_successfully_and_defaults_to_still_in_cart()
    {
        $tour = Tour::factory()->create();
        $venue = Venue::factory()->create();
        $user = User::factory()->create();

        $order = Order::create([
            'tour_id'       => $tour->id,
            'venue_id'      => $venue->id,
            'ordered_by_id' => $user->id,
            'due_date'      => '2026-07-01',
            'is_demo'       => false,
        ]);

        // 1. Assert the physical database table stores the structural columns perfectly
        $this->assertDatabaseHas('orders', [
            'id'            => $order->id,
            'tour_id'       => $tour->id,
            'venue_id'      => $venue->id,
            'ordered_by_id' => $user->id,
            'due_date'      => '2026-07-01',
        ]);

        // 2. Assert the virtual model-layer accessor resolves cleanly (Before checkout items are attached)
        $this->assertEquals('Still In Cart', $order->status);
        $this->assertEmpty($order->item_statuses);
    }
}