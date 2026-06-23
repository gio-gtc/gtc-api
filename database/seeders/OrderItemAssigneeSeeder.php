<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\OrderItem;
use Illuminate\Database\Seeder;

class OrderItemAssigneeSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Fetch all users belonging strictly to Organisation 1
        $eligibleUsers = User::where('organisation_id', 1)->get();

        if ($eligibleUsers->isEmpty()) {
            $this->command->warn('No users found for organisation ID 1. Skipping assignee seeding.');
            return;
        }

        // 2. Grab every order item currently in the database (Live + Demo)
        $orderItems = OrderItem::all();

        foreach ($orderItems as $item) {
            // 3. Determine a random assignment count between 1 and 3
            $count = rand(1, min(3, $eligibleUsers->count()));

            // 4. Pull a random collection of unique user IDs
            $randomUserIds = $eligibleUsers->random($count)->pluck('id')->toArray();

            // 5. Wire them up via the Eloquent relationship pivot
            $item->assignees()->attach($randomUserIds);
        }
    }
}