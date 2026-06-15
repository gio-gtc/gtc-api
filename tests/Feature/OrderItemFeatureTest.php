<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemStatus;
use App\Models\OrderItemBroadcastSpecification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderItemFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $staffUser;
    protected User $clientUser;
    protected Order $order;

    /**
     * Set up our baseline testing database state before each test run.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 1. Seed necessary lookup rows
        OrderItemStatus::insert([
            ['id' => 1, 'name' => 'Still In Cart'],
            ['id' => 2, 'name' => 'Unassigned'],
            ['id' => 3, 'name' => 'In Production'],
            ['id' => 4, 'name' => 'Client Review'],
            ['id' => 5, 'name' => 'Cancelled'],
            ['id' => 6, 'name' => 'Revision Requested'],
        ]);

        // 2. Create GTC Staff (Org 1) and standard client
        $this->staffUser = User::factory()->create(['organisation_id' => 1]);
        $this->clientUser = User::factory()->create(['organisation_id' => 2]);

        // 3. Create a parent order linked to the client
        $this->order = Order::factory()->create(['ordered_by_id' => $this->clientUser->id]);
    }

    /** @test */
    public function staff_can_bulk_update_dirty_fields_only()
    {
        // Arrange: Create items with an initial baseline date and status
        $spec1 = OrderItemBroadcastSpecification::create(['type' => 'Generic', 'cut' => 'Pre Sale', 'duration_seconds' => 30, 'language' => 'English', 'isci' => 'GTC000001']);
        $spec2 = OrderItemBroadcastSpecification::create(['type' => 'Generic', 'cut' => 'Pre Sale', 'duration_seconds' => 30, 'language' => 'English', 'isci' => 'GTC000002']);

        $item1 = OrderItem::factory()->create([
            'order_id' => $this->order->id,
            'due_date' => '2026-06-01',
            'order_item_status_id' => 2, // Unassigned
            'specifiable_id' => $spec1->id,
            'specifiable_type' => OrderItemBroadcastSpecification::class
        ]);

        $item2 = OrderItem::factory()->create([
            'order_id' => $this->order->id,
            'due_date' => '2026-06-01',
            'order_item_status_id' => 2,
            'specifiable_id' => $spec2->id,
            'specifiable_type' => OrderItemBroadcastSpecification::class
        ]);

        $payload = [
            'order_item_ids' => [$item1->id, $item2->id],
            'due_date' => '2026-06-30' // Modifying ONLY due_date
        ];

        // Act: Run the bulk update request authenticated as GTC Staff
        $response = $this->actingAs($this->staffUser)
            ->postJson('/api/order-items/bulk-update', $payload);

        // Assert: Verify response structures and state safety
        $response->assertStatus(200)
            ->assertJsonPath('meta.updated_items_count', 2);

        $this->assertDatabaseHas('order_items', ['id' => $item1->id, 'due_date' => '2026-06-30', 'order_item_status_id' => 2]);
        $this->assertDatabaseHas('order_items', ['id' => $item2->id, 'due_date' => '2026-06-30', 'order_item_status_id' => 2]);
    }

    /** @test */
    public function client_revision_request_creates_cloned_item_and_logs_ledger_entry()
    {
        // Arrange: Create a line item currently sitting in Client Review (Status 4)
        $specification = OrderItemBroadcastSpecification::create([
            'type' => 'Generic',
            'cut' => 'Pre Sale',
            'duration_seconds' => 30,
            'language' => 'English',
            'isci' => 'GTC000100'
        ]);

        $originalItem = OrderItem::factory()->create([
            'order_id' => $this->order->id,
            'order_item_status_id' => 4, // Client Review
            'revision_number' => 0,
            'specifiable_id' => $specification->id,
            'specifiable_type' => OrderItemBroadcastSpecification::class
        ]);

        $payload = [
            'comment' => 'The baseline mix needs more presence. Re-render.'
        ];

        // Act: Execute the revision call as the client user
        $response = $this->actingAs($this->clientUser)
            ->postJson("/api/order-items/{$originalItem->id}/revise", $payload);

        // Assert: 1. Confirm the transaction created a 200/201 response envelope
        $response->assertStatus(200);

        // 2. The historical row must be locked down as Cancelled (5)
        $this->assertDatabaseHas('order_items', [
            'id' => $originalItem->id,
            'order_item_status_id' => 5
        ]);

        // 3. A new duplicate item must exist flagged as Revision Requested (6) with a bumped index
        $this->assertDatabaseHas('order_items', [
            'order_id' => $this->order->id,
            'order_item_status_id' => 6,
            'revision_number' => 1
        ]);

        // 4. Verify the new specification record matches the R1 sequence suffix
        $this->assertDatabaseHas('order_item_broadcast_specifications', [
            'isci' => 'GTC000100R1'
        ]);

        // 5. Assert the glue ledger table holds the trail data
        $this->assertDatabaseHas('order_item_revisions', [
            'old_order_item_id' => $originalItem->id,
            'comment' => 'The baseline mix needs more presence. Re-render.'
        ]);
    }

    /** @test */
    public function batch_update_fails_completely_if_any_target_item_is_cancelled()
    {
        // Arrange: Create one clean active item and one cancelled history item
        $itemActive = OrderItem::factory()->create(['order_id' => $this->order->id, 'order_item_status_id' => 3]);
        $itemCancelled = OrderItem::factory()->create(['order_id' => $this->order->id, 'order_item_status_id' => 5]);

        $payload = [
            'order_item_ids' => [$itemActive->id, $itemCancelled->id],
            'order_item_status_id' => 4
        ];

        // Act: Attempt to modify the batch
        $response = $this->actingAs($this->staffUser)
            ->postJson('/api/order-items/bulk-update', $payload);

        // Assert: Expect database transaction failure rollback (HTTP 422)
        $response->assertStatus(422);
        
        // Confirm the active item's status rollback protection kept it safely at status 3
        $this->assertDatabaseHas('order_items', [
            'id' => $itemActive->id,
            'order_item_status_id' => 3
        ]);
    }
}