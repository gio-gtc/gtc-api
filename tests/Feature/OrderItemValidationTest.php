<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderMenuItem;
use App\Models\OrderItemStatus;
use App\Models\OrderItemBroadcastSpecs;
use App\Models\OrderItemKeyArtSpecs;
use App\Models\OrderStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class OrderItemValidationTest extends TestCase
{
    use RefreshDatabase; // Automatically resets data after test runs

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Seed the menu items blueprint mapping configuration
        $this->seed(\Database\Seeders\MenuCatalogSeeder::class);

        // 2. Create the parent OrderStatus record first to satisfy foreign key constraints
        OrderStatus::create([
            'id'   => 1,
            'name' => 'Pending'
        ]);

        // 3. Seed necessary item lifecycle statuses cleanly without duplicates
        OrderItemStatus::create([
            'id'              => 1, 
            'name'            => 'Still In Cart', 
            'order_status_id' => 1
        ]);
        
        OrderItemStatus::create([
            'id'              => 5, 
            'name'            => 'Cancelled', 
            'order_status_id' => 1
        ]);
    }

    /** @test */
    public function it_successfully_creates_a_valid_video_line_item()
    {
        $order = Order::factory()->create();
        $menuItem = OrderMenuItem::where('order_menu_category_id', 1)->first();

        // REALIGNED payload string keys to conform with custom blueprint validation rules
        $payload = [
            'order_menu_item_id' => $menuItem->id,
            'due_date'           => '2026-06-20',
            'specifications'     => [
                'type'             => 'Generic',
                'cut'              => 'Pre Sale',
                'duration_seconds' => 30,
                'language'         => 'English',
                'encoding'         => 'Station MP4 (Broadcast)'
            ]
        ];

        $response = $this->postJson("/api/orders/{$order->id}/items", $payload);

        $response->assertStatus(201);
        
        // Assert core order ledger entry was built
        $this->assertDatabaseHas('order_items', [
            'order_id'         => $order->id,
            'specifiable_type' => OrderItemBroadcastSpecs::class,
        ]);

        // Confirms that the child specification row was cleanly separated into its own table
        $this->assertDatabaseHas('order_item_broadcast_specs', [
            'type'             => 'Generic',
            'cut'              => 'Pre Sale',
            'duration_seconds' => 30,
            'language'         => 'English',
            'encoding'         => 'Station MP4 (Broadcast)'
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
                'type'             => 'Generic',
                'cut'              => 'Pre Sale',
                'duration_seconds' => '30', // ❌ String integer should fail strict validation
                'language'         => 'English',
                'encoding'         => 'Station MP4 (Broadcast)'
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
                'type'             => 'Generic',
                'cut'              => 'Pre Sale',
                'duration_seconds' => 30,
                'language'         => 'English',
                'encoding'         => 'Station MP4 (Broadcast)',
            ]
        ];

        $response = $this->postJson("/api/orders/{$order->id}/items", $payload);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['specifications.encoding']);
    }

    public function test_it_creates_key_art_line_item_when_w_and_h_are_omitted()
    {
        // 1. Arrange: Build contextual matching data
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $order = Order::factory()->create(); 

        // This bypasses the API middleware and resolves the 401 error definitively
        Sanctum::actingAs($user, ['*']);

        // 2. Act: Send the payload
        $response = $this->postJson("/api/orders/{$order->id}/items", [
            'due_date'           => '2026-07-15',
            'order_menu_item_id' => 4, 
            'specifications'     => [
                'type' => 'Key Art Package',
            ],
        ]);

        if ($response->status() !== 201) {
            $response->dump();
        }

        // 3. Assert
        $response->assertStatus(201);

        $this->assertDatabaseHas('order_items', [
            'order_id'             => $order->id,
            'order_menu_item_id'   => 4,
            'specifiable_type'     => \App\Models\OrderItemKeyArtSpecs::class,
            'order_item_status_id' => 1, 
        ]);

        $this->assertDatabaseHas('order_item_key_art_specs', [
            'type' => 'Key Art Package',
            'w'    => null,
            'h'    => null,
        ]);
    }

    /** @test */
    public function it_transitions_status_to_cancelled_on_delete()
    {
        // Generate specifiable child dependency to support controller refresh return rules
        $broadcastSpec = OrderItemBroadcastSpecs::create([
            'type' => 'Generic', 'cut' => 'Pre Sale', 'duration_seconds' => 30, 'language' => 'English'
        ]);

        // Setup an order and item inside the DB linked to seeded data configurations
        $orderItem = OrderItem::factory()->create([
            'order_item_status_id' => 1, // Active / Still In Cart
            'specifiable_id'       => $broadcastSpec->id,
            'specifiable_type'     => OrderItemBroadcastSpecs::class
        ]);

        $response = $this->deleteJson("/api/order-items/{$orderItem->id}");

        $response->assertStatus(200);
        
        // Assert it was NOT deleted from table but status updated
        $this->assertDatabaseHas('order_items', [
            'id'                   => $orderItem->id,
            'order_item_status_id' => 5 // Confirms transition to Cancelled status state string key
        ]);
    }
}