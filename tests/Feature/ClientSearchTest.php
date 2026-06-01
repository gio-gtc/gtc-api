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

    /**
     * Verify search matches client criteria.
     */
    public function test_it_can_filter_clients_by_user_name_or_email_or_organisation_name()
    {
        Sanctum::actingAs($this->adminUser);

        // Test Match Case A: Filter exclusively by user first name text segment
        $response = $this->getJson(route('clients.index', ['search' => 'Alice']));
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Alice', $response->json('data.0.first_name'));

        // Test Match Case B: Filter exclusively by email sequence string
        $response = $this->getJson(route('clients.index', ['search' => 'bob@']));
        $response->assertStatus(200);
        $this->assertEquals('Bob', $response->json('data.0.first_name'));

        // Test Match Case C: Filter deep relation matching by corporate organization name
        $response = $this->getJson(route('clients.index', ['search' => 'Universal']));
        $response->assertStatus(200);
        $this->assertEquals('Alice', $response->json('data.0.first_name'));
    }

    /**
     * Verify strict query boundary short-circuit safeguards.
     */
    public function test_it_returns_empty_results_if_search_parameter_is_under_two_characters()
    {
        Sanctum::actingAs($this->adminUser);

        // Single letter query should bypass query search processing loops
        $response = $this->getJson(route('clients.index', ['search' => 'U']));

        $response->assertStatus(200)
            ->assertExactJson(['data' => []]);
    }
}