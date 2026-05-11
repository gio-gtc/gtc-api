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
            'department'      => 'Engineering',
            'organisation_id' => 1,
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['message' => 'Profile updated successfully.']);

        // 3. Verify the database actually updated
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'first_name' => 'Updated',
            'organisation_id' => 1,
            'department'      => 'Engineering',
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

    public function test_a_user_can_update_their_basic_profile_without_changing_email()
    {
        $user = $this->loginAsUser();

        $response = $this->putJson('/api/profile', [
            'first_name'      => 'Updated',
            'last_name'       => 'Name',
            'email'           => $user->email, // Keeping email the same
            'organisation_id' => 1, 
            'department'      => 'Engineering',
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['message' => 'Profile updated successfully.']);

        $this->assertDatabaseHas('users', [
            'id'              => $user->id,
            'first_name'      => 'Updated',
            'email'           => $user->email, // Actual email remains unchanged
            'pending_email'   => null,
        ]);
    }
    
    public function test_changing_an_email_saves_it_to_pending_email_instead()
    {
        \Illuminate\Support\Facades\Mail::fake();

        $user = $this->loginAsUser();

        $response = $this->putJson('/api/profile', [
            'first_name'      => $user->first_name,
            'last_name'       => $user->last_name,
            'email'           => 'new.email@example.com', // 👈 Changing the email!
        ]);

        $response->assertOk();
        $response->assertJsonFragment([
            'message' => 'Profile updated. Please check your new inbox to verify your updated email address.'
        ]);

        $this->assertDatabaseHas('users', [
            'id'              => $user->id,
            'email'           => $user->email, // 👈 Original email is strictly protected!
            'pending_email'   => 'new.email@example.com', // 👈 New email safely waiting
        ]);

        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\VerifyPendingEmail::class);
    }

    public function test_a_user_can_verify_and_swap_their_pending_email()
    {
        // Create a user who already requested an email change
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'pending_email' => 'new@example.com'
        ]);

        // Generate a valid, cryptographically signed URL
        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'profile.verify-email',
            now()->addMinutes(60),
            ['user' => $user->id]
        );

        // Simulate the user clicking the link in their email
        $response = $this->get($url);

        // It should redirect them back to the frontend
        $response->assertRedirect();

        // The database should have swapped the emails!
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'new@example.com',
            'pending_email' => null,
        ]);
    }

    public function test_it_rejects_tampered_verification_links()
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'pending_email' => 'hacker@example.com'
        ]);

        // Generate a valid URL
        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'profile.verify-email',
            now()->addMinutes(60),
            ['user' => $user->id]
        );

        // Tamper with the URL (e.g., a hacker changing the ID or signature)
        $tamperedUrl = $url . '123';

        $response = $this->get($tamperedUrl);

        // It should reject it completely
        $response->assertStatus(403);

        // The database should NOT have changed
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'old@example.com',
            'pending_email' => 'hacker@example.com',
        ]);
    }
}
