<?php

namespace Tests\Feature;

use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssigneeControllerTest extends TestCase
{
    use RefreshDatabase; // Wipes the testing DB clean before every test run

    /** @test */
    public function it_can_list_all_assignees_for_an_order_item()
    {
        $orderItem = OrderItem::factory()->create();
        $users = User::factory()->count(2)->create();
        
        // Attach them directly to the pivot table for setup
        $orderItem->assignees()->attach($users->pluck('id'));

        $response = $this->getJson("/api/order-items/{$orderItem->id}/assignees");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email']
                ]
            ]);
    }

    /** @test */
    public function it_can_sync_assignees_to_an_order_item()
    {
        $orderItem = OrderItem::factory()->create();
        $users = User::factory()->count(3)->create();
        
        // Pick 2 users to assign
        $payload = [
            'user_ids' => [$users[0]->id, $users[1]->id]
        ];

        $response = $this->postJson("/api/order-items/{$orderItem->id}/assignees", $payload);

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');

        // Confirm database pivot records exist
        $this->assertDatabaseHas('order_item_assignee', [
            'order_item_id' => $orderItem->id,
            'user_id'       => $users[0]->id,
        ]);
    }

    /** @test */
    public function it_fails_syncing_if_user_ids_are_invalid()
    {
        $orderItem = OrderItem::factory()->create();
        
        $payload = [
            'user_ids' => [999999] // Non-existent ID
        ];

        $response = $this->postJson("/api/order-items/{$orderItem->id}/assignees", $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_ids.0']);
    }

    /** @test */
    public function it_can_detach_a_single_assignee_from_an_order_item()
    {
        $orderItem = OrderItem::factory()->create();
        $user = User::factory()->create();
        
        $orderItem->assignees()->attach($user->id);

        $response = $this->deleteJson("/api/order-items/{$orderItem->id}/assignees/{$user->id}");

        $response->assertStatus(200);

        // Confirm the entry was cleared out from the pivot table
        $this->assertDatabaseMissing('order_item_assignee', [
            'order_item_id' => $orderItem->id,
            'user_id'       => $user->id,
        ]);
    }
}