<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemStatus;
use App\Models\OrderMenuItem;
use Database\Seeders\OrderStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderItemDatabaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderStatusSeeder::class);
    }

    /**
     * Verify serialization and integrity of freeform metadata specification elements.
     */
    public function test_an_order_item_can_safely_store_and_retrieve_json_specifications()
    {
        $order = Order::factory()->create();
        $menuItem = OrderMenuItem::factory()->create();
        $unassignedStatus = OrderItemStatus::where('name', 'Unassigned')->first();

        // Pass the required dictionary primary key index
        $item = OrderItem::create([
            'order_id'             => $order->id,
            'order_menu_item_id'   => $menuItem->id,
            'order_item_status_id' => $unassignedStatus->id,
            'locked_price'         => 500,
            'specifications'       => ['encoding' => 'ProRes 422']
        ]);

        $this->assertDatabaseHas('order_items', [
            'id'                   => $item->id,
            'order_item_status_id' => $unassignedStatus->id,
        ]);

        // Validate casting layer parsing
        $this->assertEquals('ProRes 422', $item->fresh()->specifications['encoding']);
        
        // Validate our backwards-compatible JSON root text accessor strings
        $this->assertEquals('Unassigned', $item->status);
    }

    /**
     * Verify that mutating child item rows automatically re-computes parent indexing tables.
     */
    public function test_changing_item_status_automatically_recalculates_parent_order_statuses()
    {
        $order = Order::factory()->create();
        $menuItem = OrderMenuItem::factory()->create();
        
        $unassignedStatus   = OrderItemStatus::where('name', 'Unassigned')->first();
        $inProductionStatus = OrderItemStatus::where('name', 'In Production')->first();
        $deliveryStatus     = OrderItemStatus::where('name', 'Out For Delivery')->first();

        // 1. Append an Unassigned item -> Header array should populate 'New Order'
        $item1 = OrderItem::create([
            'order_id'             => $order->id,
            'order_menu_item_id'   => $menuItem->id,
            'order_item_status_id' => $unassignedStatus->id,
            'locked_price'         => 250,
        ]);

        $order->refresh();
        $this->assertContains('New Order', $order->item_statuses);
        $this->assertTrue($order->is_awaiting_assets); // Target icon warning triggers active

        // 2. Append an In Production item -> Header array must contain BOTH states simultaneously
        $item2 = OrderItem::create([
            'order_id'             => $order->id,
            'order_menu_item_id'   => $menuItem->id,
            'order_item_status_id' => $inProductionStatus->id,
            'locked_price'         => 350,
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
        
        // Ensure the missing assets boolean icon guard automatically flips to false
        $this->assertFalse($order->is_awaiting_assets);
    }
}