<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderShowDate;
use App\Models\Tour;
use App\Models\Venue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_order_is_created_with_a_default_status_of_new_order()
    {
        // Arrange core dependencies
        $tour = Tour::factory()->create();
        $venue = Venue::factory()->create();
        $user = User::factory()->create();

        // Act
        $order = Order::create([
            'tour_id' => $tour->id,
            'venue_id' => $venue->id,
            'ordered_by_id' => $user->id,
            'due_date' => '2026-07-01',
        ]);

        // Assert
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'New Order',
        ]);
    }

    public function test_an_order_can_have_a_multi_night_run_of_show_dates()
    {
        $order = Order::factory()->create();

        // Attach a 2-night run
        OrderShowDate::create(['order_id' => $order->id, 'show_date' => '2026-06-01']);
        OrderShowDate::create(['order_id' => $order->id, 'show_date' => '2026-06-02']);

        // Assert relationships count out correctly
        $this->assertEquals(2, $order->showDates()->count());
        $this->assertDatabaseHas('order_show_dates', [
            'order_id' => $order->id,
            'show_date' => '2026-06-02',
        ]);
    }
}