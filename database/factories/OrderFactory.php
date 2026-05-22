<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Tour;
use App\Models\Venue;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $statuses = ['New Order', 'In Progress', 'Canceled', 'Complete', 'Client Review'];

        return [
            'tour_id' => Tour::inRandomOrder()->first()?->id ?? Tour::factory(),
            'venue_id' => Venue::inRandomOrder()->first()?->id ?? Venue::factory(),
            'owner_id' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'status' => $this->faker->randomElement($statuses),
            'local_deliverable_email' => $this->faker->optional(0.5)->safeEmail(),
            'due_date' => $this->faker->dateTimeBetween('+1 week', '+1 month')->format('Y-m-d'),
        ];
    }
}