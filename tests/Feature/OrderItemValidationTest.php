<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderMenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OrderItemValidationTest extends TestCase
{
    use RefreshDatabase; // Automatically resets data after test runs

    protected function setUp(): void
    {
        parent::setUp();

        // Seed the menu items blueprint mapping configuration
        $this->seed(\Database\Seeders\MenuCatalogSeeder::class);
    }

    /** @test */
    public function it_successfully_creates_a_valid_video_line_item()
    {
        $order = Order::factory()->create();
        $menuItem = OrderMenuItem::where('order_menu_category_id', 1)->first();

        $payload = [
            'order_menu_item_id' => $menuItem->id,
            'due_date'           => '2026-06-20',
            'specifications'     => [
                'type'             => 'Broadcast TV Spot',
                'cut'              => 'Main Event Teaser',
                'duration_seconds' => 30,
                'language'         => 'English (US)',
                'encoding'         => 'H264-MP4 (Online or Venue)'
            ]
        ];

        $response = $this->postJson("/api/orders/{$order->id}/items", $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
        ]);
    }

    /** @test */
    public function it_rejects_string_coercion_on_durations()
    {
        $order = Order::factory()->create();
        $menuItem = OrderMenuItem::where('order_menu_category_id', 1)->first();

        $payload = [
            'order_menu_item_id' => $menuItem->id,
            'due_date'           => '2026-06-20',
            'specifications'     => [
                'type'             => 'Broadcast TV Spot',
                'cut'              => 'Main Event Teaser',
                'duration_seconds' => '30', // ❌ String integer should fail strict validation
                'language'         => 'English (US)',
                'encoding'         => 'H264-MP4 (Online or Venue)'
            ]
        ];

        $response = $this->postJson("/api/orders/{$order->id}/items", $payload);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['specifications.duration_seconds']);
    }

    /** @test */
    public function it_enforces_the_encoding_xor_rule()
    {
        $order = Order::factory()->create();
        $menuItem = OrderMenuItem::where('order_menu_category_id', 1)->first();

        $payload = [
            'order_menu_item_id' => $menuItem->id,
            'due_date'           => '2026-06-20',
            'specifications'     => [
                'type'             => 'Broadcast TV Spot',
                'cut'              => 'Main Event Teaser',
                'duration_seconds' => 30,
                'language'         => 'English (US)',
                'encoding'         => 'H264-MP4 (Online or Venue)',
                'encoding_custom'  => 'Illegal Second Option' // ❌ Providing both must fail
            ]
        ];

        $response = $this->postJson("/api/orders/{$order->id}/items", $payload);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['specifications.encoding']);
    }

    /** @test */
    public function it_transitions_status_to_cancelled_on_delete()
    {
        // Setup an order and item inside the DB
        $orderItem = OrderItem::factory()->create([
            'order_item_status_id' => 1 // Active
        ]);

        $response = $this->deleteJson("/api/order-items/{$orderItem->id}");

        $response->assertStatus(200);
        
        // Assert it was NOT deleted from table but status updated
        $this->assertDatabaseHas('order_items', [
            'id'                   => $orderItem->id,
            'order_item_status_id' => 5 // Assuming 5 is your default Cancelled fallback key
        ]);
    }
}