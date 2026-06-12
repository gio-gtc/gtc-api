<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderMenuCategory;
use App\Models\OrderMenuItem;
use App\Models\OrderItemStatus;
use App\Models\OrderShowDate;
use App\Models\Organisation;
use App\Models\Tour;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\OrderStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderDetailsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Seed our baseline dictionary matrix into the SQLite testing container memory
        $this->seed(OrderStatusSeeder::class);

        // 2. Setup organizational boundaries
        $organisation = Organisation::create(['name' => 'Interscope Records']);
        $this->user = User::factory()->create(['organisation_id' => $organisation->id]);

        // 3. Setup master relational dependencies
        $tour = Tour::factory()->create();
        $venue = Venue::factory()->create();

        // 4. Create the parent order shell container
        $this->order = Order::create([
            'tour_id' => $tour->id,
            'venue_id' => $venue->id,
            'ordered_by_id' => $this->user->id,
            'is_demo' => false
        ]);

        // 5. Attach a nested show date
        OrderShowDate::create([
            'order_id' => $this->order->id,
            'show_date' => '2026-07-20'
        ]);

        // 6. Populate catalog records
        $category = OrderMenuCategory::create(['name' => 'Merchandise Design']);
        $menuItem = OrderMenuItem::create([
            'order_menu_category_id' => $category->id,
            'name' => 'Tour Hoodie Asset Blueprint',
            'default_price' => 250.00
        ]);

        // 7. Look up the relational Unassigned record (Maps to parent "New Order")
        $unassignedStatus = OrderItemStatus::where('name', 'Unassigned')->first();

        // 8. Inject an active item into the container with its foreign key mapping
        $this->order->orderItems()->create([
            'order_menu_item_id'   => $menuItem->id,
            'locked_price'         => 250.00,
            'order_item_status_id' => $unassignedStatus->id,
        ]);
    }

    /**
     * Verify unauthenticated requests are blocked from downloading resource structures.
     */
    public function test_unauthenticated_users_cannot_view_order_details()
    {
        $response = $this->getJson(route('orders.show', $this->order->id));

        $response->assertStatus(401);
    }

    /**
     * Verify complete nested relationship data tree download succeeds with full structural validations.
     */
    public function test_authenticated_users_can_retrieve_single_order_with_complete_relations()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson(route('orders.show', $this->order->id));

        // Assert network envelope pattern and eager-loaded key requirements match exactly
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'uuid',
                    'tour_id',
                    'venue_id',
                    'item_statuses',
                    'venue',
                    'tour',
                    'client',
                    'show_dates' => [
                        '*' => ['id', 'order_id', 'show_date']
                    ],
                    'order_items' => [
                        '*' => [
                            'id',
                            'order_item_status_id',
                            'specifiable_id',
                            'specifiable_type',
                            'specifiable',
                            'status_lookup' => [
                                'id',
                                'name',
                                'order_status_id'
                            ]
                        ]
                    ]
                ]
            ]);

        // Assert exact properties inside data layer to ensure relationships match target rows
        $this->assertEquals($this->order->id, $response->json('data.id'));
        $this->assertContains('New Order', $response->json('data.item_statuses'));
        $this->assertEquals('Unassigned', $response->json('data.order_items.0.status_lookup.name'));
        $this->assertEquals('2026-07-20', $response->json('data.show_dates.0.show_date'));
        $this->assertEquals('Tour Hoodie Asset Blueprint', $response->json('data.order_items.0.order_menu_item.name'));
        $this->assertEquals('Merchandise Design', $response->json('data.order_items.0.order_menu_item.category.name'));
    }
}