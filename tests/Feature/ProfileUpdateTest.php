<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_update_their_own_profile()
    {
        // 1. Log the user in
        $user = $this->loginAsUser();

        // 2. Send the PUT request to the profile endpoint without an ID
        $response = $this->putJson('/api/profile', [
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'email' => $user->email, // Keeping the same email
            // 'organisation' => 'New Organization',
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['message' => 'Profile updated successfully.']);

        // 3. Verify the database actually updated
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'first_name' => 'Updated',
            // 'organisation' => 'New Organization',
        ]);
    }

    public function test_a_user_cannot_steal_another_users_email()
    {
        $user = $this->loginAsUser();

        // Create a different user with an email we want to test against
        User::factory()->create(['email' => 'taken@example.com']);

        // Attempt to update our own profile using the stolen email
        $response = $this->putJson('/api/profile', [
            'first_name' => 'Hacker',
            'last_name' => 'Man',
            'email' => 'taken@example.com', 
        ]);

        // It should throw a 422 error explicitly on the email field
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}