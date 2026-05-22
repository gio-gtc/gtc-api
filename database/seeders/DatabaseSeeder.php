<?php

namespace Database\Seeders;

use App\Models\Tour;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Run your new Seeder first!
        $this->call([
            RolesAndPermissionsSeeder::class,
            OrganisationTypeSeeder::class,
            CountrySeeder::class,
            OrganisationSeeder::class,
            DepartmentSeeder::class,
            VenueSeeder::class,
            MenuCatalogSeeder::class,
        ]);

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

        // TODO: Production remove everything below this line 👇
        $dummyUsers = User::factory(25)->create();
        $dummyUsers->each(function ($user) {
            $randomRole = fake()->randomElement(['Designer', 'Client']);            
            $user->assignRole($randomRole);
        });

        Tour::factory(20)->create();

        \App\Models\Order::factory(10)->create()->each(function ($order) {
            // Give each random order a 1 to 3 night run automatically
            for ($i = 0; $i < rand(1, 3); $i++) {
                \App\Models\OrderShowDate::create([
                    'order_id' => $order->id,
                    'show_date' => now()->addDays(rand(1, 30))->format('Y-m-d'),
                ]);
            }


            \App\Models\OrderItem::factory(2)->create(['order_id' => $order->id]);
        });
    }
}