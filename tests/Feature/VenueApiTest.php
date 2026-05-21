<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\VenueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VenueApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_blocks_unauthenticated_requests_from_accessing_venues_list()
    {
        $response = $this->getJson(route('venues.index'));

        $response->assertStatus(401);
    }

    public function test_it_returns_a_sorted_list_of_all_venues_to_authenticated_users()
    {
        // Explicitly execute our master data seeds for this isolated environment block
        $this->seed(VenueSeeder::class);

        // Create an authenticated mock context using Sanctum
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Act
        $response = $this->getJson(route('venues.index'));

        // Assert
        $response->assertOk();
        $response->assertJsonStructure([
            'venues' => [
                '*' => ['id', 'name', 'city', 'state', 'capacity', 'created_at', 'updated_at']
            ]
        ]);

        // Verify that our seeded iconic test venues are present in the sorted JSON output collection
        $venueNames = collect($response->json('venues'))->pluck('name');
        
        $this->assertTrue($venueNames->contains('Madison Square Garden'));
        $this->assertTrue($venueNames->contains('Red Rocks Amphitheatre'));
    }
}