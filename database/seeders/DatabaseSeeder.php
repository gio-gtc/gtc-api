<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Run your new Roles and Permissions Seeder first!
        $this->call(RolesAndPermissionsSeeder::class);

        // 2. Create your specific Super Admin User
        $adminUser = User::firstOrCreate(
            ['email' => 'gio@gtc.co'],
            [
                'first_name' => 'Gio',
                'last_name' => 'A',
                'organisation_id' => 1,
                'phone_number' => fake()->phoneNumber(),
                'password' => Hash::make('SuperPassword!'),
            ]
        );

        // Assign the Super Admin role to yourself
        if (!$adminUser->hasRole('Super Admin')) {
            $adminUser->assignRole('Super Admin');
        }

        // 3. Create dummy users for testing
        $standardAdmin = User::factory()->create([
            'email' => 'test@gtc.co',
            'first_name' => 'Test',
            'last_name' => 'Admin',
        ]);
        $standardAdmin->assignRole('Admin');

        // And create 24 regular users with no roles yet
        User::factory(24)->create();
    }
}