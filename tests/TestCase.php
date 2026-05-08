<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Creates a user, logs them in via Sanctum, and returns strictly a User model.
     * * @return \App\Models\User
     */
    protected function loginAsUser(): User
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum');

        return $user;
    }
}