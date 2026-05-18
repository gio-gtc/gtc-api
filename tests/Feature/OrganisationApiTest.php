<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\OrganisationType;
use App\Models\User;
use Database\Seeders\OrganisationTypeSeeder;
use Database\Seeders\CountrySeeder;
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

    public function test_can_fetch_all_organisations_with_relationships(): void
    {
        // 1. Create a dummy organisation
        $organisation = Organisation::factory()->create([
            'name' => 'Heavyweight Corp'
        ]);
        
        // Ensure it has a type attached so we can test the relationship
        $type = \App\Models\OrganisationType::first(); // Assuming seeder ran in setUp
        if ($type) {
            $organisation->types()->attach($type->id);
        }

        // 2. Hit the standard index route
        $response = $this->actingAs($this->user)->getJson('/api/organisations');

        // 3. Verify it returns the full payload with relationships
        $response->assertStatus(200)
            ->assertJsonStructure([
                'organisations' => [
                    '*' => [
                        'id', 
                        'name', 
                        'country', // Must have country object
                        'types'    // Must have types array
                    ]
                ]
            ])
            ->assertJsonFragment(['name' => 'Heavyweight Corp']);
    }

    public function test_can_search_organisations_for_typeahead(): void
    {
        // 1. Create specific dummy data to test the search filter
        Organisation::factory()->create(['name' => 'Global Tour Creatives']);
        Organisation::factory()->create(['name' => 'Global Dynamics']);
        Organisation::factory()->create(['name' => 'Acme Corp']); // Should not be found

        // 2. Hit the index route with the search parameter
        $response = $this->actingAs($this->user)->getJson('/api/organisations?search=Global');

        // 3. Verify it ONLY returns the matching results
        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Global Tour Creatives'])
            ->assertJsonFragment(['name' => 'Global Dynamics'])
            ->assertJsonMissing(['name' => 'Acme Corp']); // Acme is successfully filtered out

        // 4. Verify the payload is lightweight (no relationships loaded)
        $firstResult = $response->json('organisations.0');
        $this->assertArrayHasKey('id', $firstResult);
        $this->assertArrayHasKey('name', $firstResult);
        $this->assertArrayNotHasKey('country', $firstResult); // Should NOT have the country relationship
        $this->assertArrayNotHasKey('types', $firstResult);   // Should NOT have the types relationship
    }
    
    public function test_can_create_organisation_with_multiple_types(): void
    {
        // 1. Assuming your setUp handles seeding types 1 and 2
        $type1 = \App\Models\OrganisationType::find(1);
        $type2 = \App\Models\OrganisationType::find(2);

        $payload = [
            'name' => 'Multi-Type Agency',
            'types' => [$type1->id, $type2->id],
            'currency_code' => 'USD',
        ];

        // 2. Hit the live endpoint
        $response = $this->actingAs($this->user)->postJson('/api/organisations', $payload);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('organisations_otypes', [
            'organisation_id' => $response->json('organisation.id'),
            'organisation_type_id' => $type1->id
        ]);
        
        $this->assertDatabaseHas('organisations_otypes', [
            'organisation_id' => $response->json('organisation.id'),
            'organisation_type_id' => $type2->id
        ]);
    }
}