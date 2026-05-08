<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Mail\UserInvitedMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Foundation\Testing\RefreshDatabase;

// TODO: Add organisation connection organisation_id
class UserInviteTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_users_cannot_invite_new_users()
    {
        $response = $this->postJson('/api/users/invite', [
            'first_name' => 'Hacker',
            'last_name' => 'Man',
            'email' => 'hacker@example.com',
            // 'organisation' => null,
        ]);

        $response->assertUnauthorized();
    }

    public function test_the_invite_endpoint_rejects_invalid_or_missing_data()
    {
        $this->loginAsUser();

        $response = $this->postJson('/api/users/invite', []);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors([
                     'first_name', 
                     'last_name', 
                     'email', 
                    //  'organisation'
                 ]);
    }

    public function test_it_prevents_inviting_a_user_with_an_email_that_already_exists()
    {
        $this->loginAsUser();
        
        // Create an existing user in the database
        User::factory()->create(['email' => 'duplicate@example.com']);

        $response = $this->postJson('/api/users/invite', [
            'first_name' => 'New',
            'last_name' => 'User',
            'email' => 'duplicate@example.com',
            // 'organisation' => null,
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['email']);
    }

    public function test_an_admin_can_successfully_invite_a_new_user_and_trigger_an_email()
    {
        Mail::fake();

        $this->loginAsUser();

        $payload = [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            // 'organisation' => null,
            'phone_number' => '555-1234',
            'job_title' => 'Director',
        ];

        $response = $this->postJson('/api/users/invite', $payload);

        $response->assertOk();

        $this->assertDatabaseHas('users', [
            'email' => 'jane@example.com',
            'first_name' => 'Jane',
            // 'organisation' => null,
            'phone_number' => '555-1234',
        ]);

        // Verify the email was actually queued to send
        Mail::assertSent(UserInvitedMail::class, function ($mail) {
            return $mail->hasTo('jane@example.com');
        });
    }
}