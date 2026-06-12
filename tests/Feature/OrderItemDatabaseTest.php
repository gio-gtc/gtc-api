<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderMenuItem;
use App\Models\OrderItemStatus;
use App\Models\OrderItemBroadcastSpecification;
use App\Models\User;
use Database\Seeders\OrderStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderItemDatabaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed the system order and item status lookups
        $this->seed(OrderStatusSeeder::class);
    }

    /** @test */
    public function an_order_item_can_safely_store_and_retrieve_polymorphic_broadcast_specifications(): void
    {
        // 1. Arrange baseline infrastructure requirements
        $user = User::factory()->create();
        
        $order = Order::create([
            'title'   => 'Test Campaign',
            'user_id' => $user->id,
        ]);

        $unassignedStatus = OrderItemStatus::where('name', 'Unassigned')->first();
        $menuItem = OrderMenuItem::factory()->create(['order_menu_category_id' => 1]);

        // 2. Act: Create the standalone specification child row
        $broadcastSpec = OrderItemBroadcastSpecification::create([
            'type'             => 'Generic',
            'cut'              => 'Pre Sale',
            'duration_seconds' => 30,
            'language'         => 'English',
            'encoding'         => 'Station MP4 (Broadcast)',
            'isci'             => 'ISCI-TEST1234',
        ]);

        // Link the item to the child row via the morph parameters pair
        $orderItem = OrderItem::create([
            'order_id'             => $order->id,
            'order_menu_item_id'   => $menuItem->id,
            'order_item_status_id' => $unassignedStatus->id,
            'locked_price'         => 250.00,
            'specifiable_id'       => $broadcastSpec->id,
            'specifiable_type'     => OrderItemBroadcastSpecification::class,
        ]);

        // 3. Assert: Verify relationships pull through cleanly
        $freshItem = $orderItem->fresh();

        $this->assertNotNull($freshItem->specifiable);
        $this->assertInstanceOf(OrderItemBroadcastSpecification::class, $freshItem->specifiable);
        $this->assertEquals('Generic', $freshItem->specifiable->type);
        $this->assertEquals('ISCI-TEST1234', $freshItem->specifiable->isci);
    }

    /** @test */
    public function changing_item_status_automatically_recalculates_parent_order_statuses()
    {
        $order = Order::factory()->create();
        
        $unassignedStatus   = OrderItemStatus::where('name', 'Unassigned')->first();
        $inProductionStatus = OrderItemStatus::where('name', 'In Production')->first();
        $deliveryStatus     = OrderItemStatus::where('name', 'Out For Delivery')->first();

        // 1. Append an Unassigned item using our clean factory rules
        $item1 = OrderItem::factory()->create([
            'order_id'             => $order->id,
            'order_item_status_id' => $unassignedStatus->id,
        ]);

        $order->refresh();
        $this->assertContains('New Order', $order->item_statuses);

        // 2. Append an In Production item -> Header array must contain BOTH states simultaneously
        $item2 = OrderItem::factory()->create([
            'order_id'             => $order->id,
            'order_item_status_id' => $inProductionStatus->id,
        ]);

        $order->refresh();
        $this->assertContains('New Order', $order->item_statuses);
        $this->assertContains('In Progress', $order->item_statuses);

        // 3. Transition all items to Out For Delivery -> Matrix should clean up and pivot to Complete
        $item1->update(['order_item_status_id' => $deliveryStatus->id]);
        $item2->update(['order_item_status_id' => $deliveryStatus->id]);

        $order->refresh();
        $this->assertContains('Complete', $order->item_statuses);
        $this->assertNotContains('New Order', $order->item_statuses);
        $this->assertNotContains('In Progress', $order->item_statuses);
    }
}