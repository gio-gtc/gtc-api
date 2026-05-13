<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_fetch_roles_without_super_admin()
    {
        // 1. Create the roles
        Role::create(['name' => 'Super Admin']);
        Role::create(['name' => 'Admin']);
        Role::create(['name' => 'Client']);

        // 2. Create a dummy user and act as them
        /** @var \App\Models\User $user */
        $user = User::factory()->create();

        // 3. Hit the endpoint
        $response = $this->actingAs($user)->getJson('/api/roles');

        // 4. Assertions
        $response->assertOk();
        
        // Assert 'Super Admin' is missing, but 'Admin' and 'Client' are present
        $response->assertJsonMissing(['Super Admin']);
        $response->assertJsonFragment(['Admin']);
        $response->assertJsonFragment(['Client']);
        
        // Assert exactly 2 roles were returned
        $response->assertJsonCount(2, 'roles');
    }
}