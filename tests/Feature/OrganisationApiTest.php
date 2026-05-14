<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\OrganisationType;
use App\Models\User;
use Database\Seeders\OrganisationTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganisationApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 1. Seed the test database with your real, static Organisation Types
        $this->seed(OrganisationTypeSeeder::class);

        // 2. Create a user to bypass the auth middleware
        $this->user = User::factory()->create();
    }

    public function test_can_fetch_organisation_types(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/organisation-types');

        // Verify it returns 200 and contains your real seeded static types
        $response->assertStatus(200)
                 ->assertJsonFragment(['name' => 'Advertising Agency'])
                 ->assertJsonFragment(['name' => 'Promoter'])
                 ->assertJsonFragment(['name' => 'Graphic Designer']);
    }

    public function test_can_create_organisation_with_types_and_emails(): void
    {
        // Grab the real seeded types from the database
        $type1 = OrganisationType::where('name', 'Advertising Agency')->first();
        $type2 = OrganisationType::where('name', 'Promoter')->first();

        $payload = [
            'name' => 'Test Corp',
            'billing_address' => '123 Test St',
            'credit_limit' => 10000.50,
            'types' => [$type1->id, $type2->id],
            'accounts_payable_emails' => [
                'billing@test.com',
                'admin@test.com'
            ]
        ];

        $response = $this->actingAs($this->user)->postJson('/api/organisations', $payload);

        $response->assertStatus(201)
                 ->assertJsonFragment(['name' => 'Test Corp'])
                 ->assertJsonFragment(['billing@test.com']); 

        $this->assertDatabaseHas('organisations', [
            'name' => 'Test Corp',
            'credit_limit' => 10000.50,
        ]);

        $orgId = $response->json('organisation.id');

        // Verify the pivot table linked the real seeded type IDs
        $this->assertDatabaseHas('organisations_otypes', [
            'organisation_id' => $orgId,
            'organisation_type_id' => $type1->id
        ]);
        $this->assertDatabaseHas('organisations_otypes', [
            'organisation_id' => $orgId,
            'organisation_type_id' => $type2->id
        ]);
    }

    public function test_can_update_organisation_and_sync_types(): void
    {
        $type1 = OrganisationType::where('name', 'Advertising Agency')->first();
        $type2 = OrganisationType::where('name', 'Promoter')->first();

        $organisation = Organisation::create([
            'name' => 'Old Name',
            'accounts_payable_emails' => ['old@test.com']
        ]);
        
        $organisation->types()->attach($type1->id);

        // Update payload: swap Type 1 out for Type 2
        $payload = [
            'name' => 'New Name',
            'types' => [$type2->id],
            'accounts_payable_emails' => ['new@test.com']
        ];

        $response = $this->actingAs($this->user)->putJson("/api/organisations/{$organisation->id}", $payload);

        $response->assertStatus(200)
                 ->assertJsonFragment(['name' => 'New Name']);

        // Verify sync worked: Type 1 should be gone, Type 2 should exist
        $this->assertDatabaseMissing('organisations_otypes', [
            'organisation_id' => $organisation->id,
            'organisation_type_id' => $type1->id
        ]);
        $this->assertDatabaseHas('organisations_otypes', [
            'organisation_id' => $organisation->id,
            'organisation_type_id' => $type2->id
        ]);
    }

    public function test_can_delete_organisation(): void
    {
        $organisation = Organisation::create([
            'name' => 'Doomed Corp',
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/api/organisations/{$organisation->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('organisations', [
            'id' => $organisation->id
        ]);
    }
}