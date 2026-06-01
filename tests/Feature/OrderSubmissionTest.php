<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderMenuCategory;
use App\Models\OrderMenuItem;
use App\Models\Organisation;
use App\Models\Tour;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Order $order;
    private OrderMenuItem $menuItem;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Setup client corporate layout infrastructure
        $organisation = Organisation::create([
            'name' => 'Atlantic Records',
            'credit_terms' => 'Net 30'
        ]);

        $this->user = User::factory()->create([
            'organisation_id' => $organisation->id
        ]);

        // 2. Setup project containers
        $tour = Tour::factory()->create();
        $venue = Venue::factory()->create();

        $this->order = Order::create([
            'tour_id' => $tour->id,
            'venue_id' => $venue->id,
            'ordered_by_id' => $this->user->id,
            'status' => 'New Order'
        ]);

        // 3. Setup catalog items
        $category = OrderMenuCategory::create(['name' => 'Video Production']);
        $this->menuItem = OrderMenuItem::create([
            'order_menu_category_id' => $category->id,
            'name' => '15s Social Teaser',
            'default_price' => 450.00
        ]);
    }

    /**
     * Verify complete checkout lifecycle execution.
     */
    public function test_it_successfully_submits_an_order_and_generates_a_held_invoice()
    {
        Sanctum::actingAs($this->user);

        // Step A: Append a line item to the active cart
        $this->postJson(route('orders.items.store', $this->order->id), [
            'order_menu_item_id' => $this->menuItem->id,
            'due_date' => now()->addWeeks(2)->format('Y-m-d')
        ])->assertStatus(201);

        // Step B: Submit checkout container parameters
        $response = $this->postJson(route('orders.submit', $this->order->id));

        // Step C: Confirm correct network structure and pipeline transition states
        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'order' => ['id', 'status', 'order_items'],
                    'invoice' => ['id', 'document_number', 'status', 'payment_due', 'lines']
                ]
            ]);

        // Assert database values advanced matching Title Case regulations
        $this->assertDatabaseHas('order_items', [
            'order_id' => $this->order->id,
            'status' => 'Unassigned'
        ]);

        // Assert financial document sequences initialized correctly
        $this->assertDatabaseHas('invoices', [
            'organisation_id' => $this->user->organisation_id,
            'document_number' => 1,
            'status' => 'Held'
        ]);

        // Assert snapshot decoupling preserves immutable catalog reference balances
        $this->assertDatabaseHas('invoice_lines', [
            'description' => '15s Social Teaser',
            'price' => 450.00
        ]);
    }

    /**
     * Verify demo-blueprint safety guard boundaries.
     */
    public function test_submitting_a_demo_order_advances_item_states_but_skips_billing()
    {
        Sanctum::actingAs($this->user);

        // Convert order instance into a showcase template configuration
        $this->order->update(['is_demo' => true]);

        // Add a line item to the cart
        $this->postJson(route('orders.items.store', $this->order->id), [
            'order_menu_item_id' => $this->menuItem->id,
            'due_date' => now()->addWeeks(2)->format('Y-m-d')
        ])->assertStatus(201);

        // Submit checkout container
        $response = $this->postJson(route('orders.submit', $this->order->id));

        $response->assertStatus(200);

        // Line item states must still advance to Unassigned
        $this->assertDatabaseHas('order_items', [
            'order_id' => $this->order->id,
            'status' => 'Unassigned'
        ]);

        // Guard assertion: Ledger tables must remain completely empty
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('invoice_lines', 0);
    }

    /**
     * Verify empty cart protection limits.
     */
    public function test_it_prevents_submission_of_orders_with_empty_carts()
    {
        Sanctum::actingAs($this->user);

        // Attempt checkout on an empty cart container shell
        $response = $this->postJson(route('orders.submit', $this->order->id));

        $response->assertStatus(409)
            ->assertJson([
                'message' => 'Conflict: No items found in cart for this order context.'
            ]);
    }
}