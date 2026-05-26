<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderMenuCategory;
use App\Models\OrderMenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderItemDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_order_item_can_safely_store_and_retrieve_json_specifications()
    {
        $category = OrderMenuCategory::create(['name' => 'Broadcast Video']);
        $menuItem = OrderMenuItem::create([
            'order_menu_category_id' => $category->id,
            'name' => '30s Trailer',
            'default_price' => 500.00
        ]);
        $order = Order::factory()->create();

        // Act - Inject custom array properties into specs column
        $item = OrderItem::create([
            'order_id' => $order->id,
            'order_menu_item_id' => $menuItem->id,
            'price_locked' => 500.00,
            'specifications' => [
                'encoding' => 'ProRes 422',
                'isci' => 'TEST1234'
            ]
        ]);

        // Assert - Pull record directly from database storage
        $freshItem = OrderItem::find($item->id);

        $this->assertEquals('ProRes 422', $freshItem->specifications['encoding']);
        $this->assertEquals('TEST1234', $freshItem->specifications['isci']);
        $this->assertIsArray($freshItem->specifications);
    }

    public function test_multiple_creative_users_can_be_assigned_to_a_single_order_item()
    {
        $orderItem = OrderItem::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Act - Attach multiple users through our relational model pivot
        $orderItem->assignees()->attach([$user1->id, $user2->id]);

        // Assert
        $this->assertEquals(2, $orderItem->assignees()->count());
        $this->assertDatabaseHas('order_item_user', [
            'order_item_id' => $orderItem->id,
            'user_id' => $user1->id
        ]);
    }
}