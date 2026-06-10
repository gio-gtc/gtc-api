<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $clientA;
    private User $clientB;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup internal GTC corporate structure shell
        $gtcOrg = Organisation::create(['id' => 1, 'name' => 'GTC Internal HQ']);
        $this->adminUser = User::factory()->create(['organisation_id' => $gtcOrg->id]);

        // Setup decoupled external testing profile entities
        $orgA = Organisation::create(['name' => 'Universal Music Group']);
        
        $this->clientA = User::factory()->create([
            'first_name' => 'Alice',
            'last_name' => 'Cooper',
            'email' => 'alice@universal.com',
            'organisation_id' => $orgA->id
        ]);

        $orgB = Organisation::create(['name' => 'Warner Records']);
        $this->clientB = User::factory()->create([
            'first_name' => 'Bob',
            'last_name' => 'Marley',
            'email' => 'bob@warner.com',
            'organisation_id' => $orgB->id
        ]);
    }

    /**
     * Verify internal GTC personnel are blocked from exposure filters.
     */
    public function test_it_never_includes_internal_gtc_staff_in_search_results()
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson(route('clients.index'));

        $response->assertStatus(200);
        
        $returnedIds = collect($response->json('data'))->pluck('id');
        
        // Internal admin user must be fully excluded
        $this->assertFalse($returnedIds->contains($this->adminUser->id));
        $this->assertTrue($returnedIds->contains($this->clientA->id));
        $this->assertTrue($returnedIds->contains($this->clientB->id));
    }

    public function it_returns_all_clients_when_no_search_query_is_provided()
    {
        // Create external client records (organisation_id = 2)
        User::factory()->count(3)->create(['organisation_id' => 2]);

        $response = $this->getJson('/api/clients');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data'); // Bypasses validation, returns everyone
    }

    /** @test */
    public function it_filters_clients_correctly_with_a_valid_search_query()
    {
        User::factory()->create(['first_name' => 'Alexander', 'organisation_id' => 2]);
        User::factory()->create(['first_name' => 'Miles', 'organisation_id' => 2]);

        $response = $this->getJson('/api/clients?q=Alex');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.first_name', 'Alexander');
    }

    /** @test */
    public function it_returns_a_422_error_if_search_query_is_under_two_characters()
    {
        $response = $this->getJson('/api/clients?q=A');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }
}