<?php

namespace Tests\Feature;

use App\Models\User;
use App\Mail\UserInvitedMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;
use Spatie\Permission\Models\Role;

class UserOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_invite_new_user_and_email_is_sent()
    {
        Mail::fake();
        Role::create(['name' => 'Client']);

        /** @var \App\Models\User $admin */
        $admin = User::factory()->create();

        $payload = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            // 'organisation' => 'GTC Force',
            'role' => 'Client'
        ];

        // 👉 Use actingAs() to simulate the logged-in admin
        $response = $this->actingAs($admin)->postJson('/api/users/invite', $payload);

        $response->assertStatus(200)
            ->assertJson(['message' => 'User invited successfully.']);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'email_verified_at' => null,
        ]);

        Mail::assertSent(UserInvitedMail::class, function ($mail) {
            return $mail->hasTo('john@example.com');
        });
    }

    public function test_invite_fails_if_email_already_exists()
    {
        /** @var \App\Models\User $admin */
        $admin = User::factory()->create();
        User::factory()->create(['email' => 'existing@example.com']);

        $payload = [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'existing@example.com', // Duplicate!
            'organisation' => 'Another Org',
        ];

        $response = $this->actingAs($admin)->postJson('/api/users/invite', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_set_password_and_verify_account()
    {
        // This is the public endpoint, so no actingAs() is needed here!
        /** @var \App\Models\User $user */
        $user = User::factory()->create([
            'email' => 'setup@example.com',
            'password' => Hash::make('dummy-password'),
            'email_verified_at' => null,
        ]);

        /** @var \Illuminate\Auth\Passwords\PasswordBroker $broker */
        $broker = Password::broker();
        $token = $broker->createToken($user);

        $payload = [
            'email' => $user->email,
            'token' => $token,
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ];

        $response = $this->postJson('/api/users/set-password', $payload);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Password successfully set.']);

        $user->refresh();

        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('NewSecurePassword123!', $user->password));
    }
}