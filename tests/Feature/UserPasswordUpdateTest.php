<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserPasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $response = $this->actingAs($user)->putJson('/api/password', [
            'current_password'      => 'old-password',
            'password'              => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['message' => 'Password updated successfully.']);
        
        $this->assertTrue(Hash::check('new-secure-password', $user->fresh()->password));
    }

    public function test_correct_current_password_must_be_provided()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $response = $this->actingAs($user)->putJson('/api/password', [
            'current_password'      => 'wrong-password', 
            'password'              => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['current_password']);
    }

    public function test_new_passwords_must_match()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $response = $this->actingAs($user)->putJson('/api/password', [
            'current_password'      => 'old-password',
            'password'              => 'new-secure-password',
            'password_confirmation' => 'different-password', 
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }
}