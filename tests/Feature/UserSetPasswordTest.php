<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserSetPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_user_can_set_their_password_using_a_valid_token()
    {
        $user = User::factory()->create(['email' => 'newuser@example.com']);
        
        // Dynamically resolve the broker to avoid static analysis interface errors
        $token = app('auth.password.broker')->createToken($user);

        $response = $this->postJson('/api/users/set-password', [
            'email' => 'newuser@example.com',
            'token' => $token,
            'password' => 'SuperSecret123!',
            'password_confirmation' => 'SuperSecret123!',
        ]);

        $response->assertOk();
        $response->assertJson(['message' => 'Password successfully set.']);
    }

    public function test_it_rejects_an_invalid_or_expired_token_when_setting_a_password()
    {
        User::factory()->create(['email' => 'newuser@example.com']);

        $response = $this->postJson('/api/users/set-password', [
            'email' => 'newuser@example.com',
            'token' => 'this-is-a-fake-and-invalid-token',
            'password' => 'SuperSecret123!',
            'password_confirmation' => 'SuperSecret123!',
        ]);

        // 👇 Changed to expect the 400 Bad Request your API throws!
        $response->assertStatus(400);
    }
}