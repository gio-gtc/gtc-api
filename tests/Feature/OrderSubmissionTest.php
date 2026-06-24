<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderMenuCategory;
use App\Models\OrderMenuItem;
use App\Models\OrderItemStatus;
use App\Models\Organisation;
use App\Models\Tour;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\OrderStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

        // 1. Seed our baseline dictionary matrix into the SQLite testing memory space
        $this->seed(OrderStatusSeeder::class);

        // Ensure your new menu catalog seed data is present for the checkout run
        $this->seed(\Database\Seeders\MenuCatalogSeeder::class);
        
        // Grab the category 1 menu item that was seeded
        $this->menuItem = \App\Models\OrderMenuItem::where('order_menu_category_id', 1)->first();
        $this->order = \App\Models\Order::factory()->create();

        // 2. Setup testing organizational boundaries with credit terms
        $organisation = Organisation::create([
            'name' => 'GTC Test Label Group',
            'credit_terms' => 'Net 30'
        ]);
        
        $this->user = User::factory()->create(['organisation_id' => $organisation->id]);
        
        $tour = Tour::factory()->create();
        $venue = Venue::factory()->create();

        // 3. Create parent order checkout container
        $this->order = Order::create([
            'tour_id' => $tour->id,
            'venue_id' => $venue->id,
            'ordered_by_id' => $this->user->id,
            'is_demo' => false
        ]);

        // 4. Populate menu item blueprint details
        $category = OrderMenuCategory::create(['name' => 'Video Assets']);
        $this->menuItem = OrderMenuItem::factory()->create([
            'order_menu_category_id' => 1, // Forces Category 1 validation alignment
            'form_blueprint' => [
                'encodings' => ['H264-MP4 (Online or Venue)'],
                'types' => [
                    'Broadcast TV Spot' => [
                        'cuts'      => ['Main Event Teaser'],
                        'durations' => [30],
                        'languages' => ['English (US)'] // Matches your payload string exactly
                    ]
                ]
            ]
        ]);

        // 5. Initialize the database document sequencing tracker for isolated test checks
        DB::table('invoice_document_sequences')->insert([
            'sequence_key' => 'invoice',
            'last_value'   => 975949,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    /**
     * Verify complete cart checkout operations, structural transitions, and auto-billing.
     */
    public function test_it_successfully_submits_an_order_and_generates_a_held_invoice()
    {
        Sanctum::actingAs($this->user);

        // Step A: Add a line item to the active cart container
        $this->postJson(route('orders.items.store', $this->order->id), [
            'order_menu_item_id' => $this->menuItem->id,
            'due_date' => now()->addWeeks(2)->format('Y-m-d'),
            'specifications' => [
                'type'             => 'Broadcast TV Spot',
                'cut'              => 'Main Event Teaser',
                'duration_seconds' => 30,
                'language'         => 'English (US)',
                'encoding'         => ['H264-MP4 (Online or Venue)']
            ]
        ])->assertStatus(201);

        // Step B: Submit checkout container parameters
        $response = $this->postJson(route('orders.submit', $this->order->id));

        // Step C: Confirm correct network structure and pipeline transition states
        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'order' => [
                        'id', 
                        'item_statuses', 
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
                    ],
                    'invoice' => [
                        'id',
                        'document_number',
                        'status',
                        'lines' => [
                            '*' => ['id', 'invoice_id', 'order_item_id', 'price']
                        ]
                    ]
                ]
            ]);

        // Verify the parent computing fields and invoice details sequence numbers
        $this->assertContains('Unassigned', $response->json('data.order.item_statuses'));
        $this->assertEquals('975950', $response->json('data.invoice.document_number'));
        $this->assertEquals('Held', $response->json('data.invoice.status'));
        $this->assertEquals(
            'Broadcast TV Spot Main Event Teaser :30',
            $response->json('data.invoice.lines.0.description')
        );

        // Look up the relational Unassigned lookup record row ID
        $unassignedStatus = OrderItemStatus::where('name', 'Unassigned')->first();

        // Assert database records using the new relation key column, NOT the deleted raw string 'status' column
        $this->assertDatabaseHas('order_items', [
            'order_id'             => $this->order->id,
            'order_item_status_id' => $unassignedStatus->id
        ]);

        $this->assertDatabaseHas('invoices', [
            'document_number' => '975950',
            'status'          => 'Held'
        ]);
    }
}