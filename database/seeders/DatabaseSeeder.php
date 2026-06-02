<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Run Core Production Data, Setup Engine Modules, and Status Dictionary
        $this->call([
            RolesAndPermissionsSeeder::class,
            OrganisationTypeSeeder::class,
            CountrySeeder::class,
            OrganisationSeeder::class,
            DepartmentSeeder::class,
            VenueSeeder::class,
            MenuCatalogSeeder::class,
            OrderStatusSeeder::class,
        ]);

        // 2. Create Handcrafted Super Admin User Profile
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

        if (!$adminUser->hasRole('Super Admin')) {
            $adminUser->assignRole('Super Admin');
        }

        // 3. Create Specific Handcrafted Testing Target Accounts
        $standardAdmin = User::factory()->create([
            'email' => 'gio@adm.in',
            'first_name' => 'Test',
            'last_name' => 'Admin',
        ]);
        $standardAdmin->assignRole('Admin');

        $standardSupervisor = User::factory()->create([
            'email' => 'gio@supervis.or',
            'first_name' => 'Test',
            'last_name' => 'Supervisor',
        ]);
        $standardSupervisor->assignRole('Supervisor');

        $standardDesigner = User::factory()->create([
            'email' => 'gio@design.er',
            'first_name' => 'Test',
            'last_name' => 'Designer',
        ]);
        $standardDesigner->assignRole('Designer');

        $standardClient = User::factory()->create([
            'email' => 'gio@clie.nt',
            'first_name' => 'Test',
            'last_name' => 'Client',
        ]);
        $standardClient->assignRole('Client');

        // =========================================================================
        // TODO: Production Launch Checklist: Delete or comment out the line below!
        // =========================================================================
        $this->call([
            MockOrderSeeder::class, // 🧪 Attaches all factory orders, loops, and assignments
        ]);
    }
}