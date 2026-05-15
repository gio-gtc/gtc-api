<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\OrganisationType;
use App\Models\User;
use Database\Seeders\OrganisationTypeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferenceDataApiTest extends TestCase
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
        $response = $this->actingAs($this->user)->getJson('/api/reference-data');

        // Verify it returns 200 and contains your real seeded static types
        $response->assertStatus(200)
            ->assertJsonStructure([
                'org_types',
            ])
            ->assertJsonFragment(['name' => 'Advertising Agency']) 
            ->assertJsonFragment(['name' => 'Advertising Agency'])
            ->assertJsonFragment(['name' => 'Promoter'])
            ->assertJsonFragment(['name' => 'Graphic Designer']);
    }

    public function test_can_fetch_reference_data(): void
    {
        // Seed the countries so we have currency codes to test
        $this->seed(CountrySeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $response = $this->actingAs($this->user)->getJson('/api/reference-data');

        // Verify it returns 200 and matches your new exact structure
        $response->assertStatus(200)
            ->assertJsonStructure([
                'org_types',
                'countries',
                'currency_codes',
                'roles'
            ])
            ->assertJsonFragment(['name' => 'Advertising Agency']) 
            ->assertJsonFragment(['name' => 'United States', 'currency_code' => 'USD']) 
            ->assertJsonFragment(['USD']);
        $this->assertContains('Admin', $response->json('roles'));

    }
}