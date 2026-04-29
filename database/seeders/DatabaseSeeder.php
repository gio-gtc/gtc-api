<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Clear cached roles/permissions (Good practice when seeding)
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. Define the core permissions your dashboard will use to hide/show UI
        // TODO: This is just example permissions. Edit for actual app needs.
        $permissions = [
            'view dashboard',
            'manage tours',
            'manage venues',
            'manage users',
            'edit media'
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // 3. Create Roles and assign permissions
        $adminRole = Role::firstOrCreate(['name' => 'Super Admin']);
        // Super Admins get everything
        $adminRole->givePermissionTo(Permission::all());

        $editorRole = Role::firstOrCreate(['name' => 'Editor']);
        // Editors get a restricted subset
        $editorRole->givePermissionTo(['manage tours', 'edit media', 'manage users', 'manage venues',]);

        // 4. Create your Master Admin User
        $adminUser = User::firstOrCreate(
            ['email' => 'gio@gtc.co'], // <-- Use your actual email
            [
                'first_name' => 'Gio',
                'last_name' => 'A',
                'password' => Hash::make('GTCPassword123!'), // <-- Use your actual password
            ]
        );

        // 5. Assign the Super Admin role to your user
        if (!$adminUser->hasRole('Super Admin')) {
            $adminUser->assignRole($adminRole);
        }
    }
}