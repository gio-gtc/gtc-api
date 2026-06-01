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

        Tour::factory(5)->create();

        $tours = \App\Models\Tour::all();
        $tourCount = $tours->count();

        // If no tours exist yet, generate a fallback baseline pool of 4 tours
        if ($tourCount === 0) {
            $tours = \App\Models\Tour::factory(4)->create();
            $tourCount = $tours->count();
        }

        // 2. Construct a strict pool of 40 item statuses (20 orders x 2 items)
        // This guarantees exactly 3 'Canceled' states and balances all others well above twice.
        $statusPool = array_merge(
            ['Canceled', 'Canceled', 'Canceled'],
            array_fill(0, 7, 'Still In Cart'),
            array_fill(0, 7, 'Unassigned'),
            array_fill(0, 8, 'In Production'),
            array_fill(0, 7, 'Client Review'),
            array_fill(0, 8, 'Out For Delivery')
        );

        // Shuffle the pool so statuses are mixed randomly across different orders
        shuffle($statusPool);

        // 3. Seed the 20 orders sequentially
        for ($i = 0; $i < 20; $i++) {
            
            // 🚀 EVEN SPLIT: Use the modulo operator to rotate through tours perfectly evenly
            $assignedTour = $tours[$i % $tourCount];

            $order = \App\Models\Order::factory()->create([
                'tour_id' => $assignedTour->id,
            ]);

            // Give each order a 1 to 3 night run automatically
            $runNights = rand(1, 3);
            for ($j = 0; $j < $runNights; $j++) {
                \App\Models\OrderShowDate::create([
                    'order_id'  => $order->id,
                    'show_date' => now()->addDays(rand(1, 30))->format('Y-m-d'),
                ]);
            }

            // Create exactly 2 items per order pulling directly from our controlled status pool
            for ($k = 0; $k < 2; $k++) {
                $allocatedStatus = array_pop($statusPool) ?? 'Unassigned';

                \App\Models\OrderItem::factory()->create([
                    'order_id' => $order->id,
                    'status'   => $allocatedStatus, // Sets Title Case via the model's mutator
                ]);
            }
        }

        $this->call([
            OrderItemAssigneeSeeder::class,
        ]);
    }
}