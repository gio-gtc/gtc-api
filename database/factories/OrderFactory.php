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
            'ordered_by_id' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'local_deliverable_email' => $this->faker->optional(0.5)->safeEmail(),
            'due_date' => $this->faker->dateTimeBetween('+1 week', '+1 month')->format('Y-m-d'),
            'ticket_outlets'       => $this->faker->boolean(70) ? 'Ticketmaster / Venue Box Office Box ' . $this->faker->randomDigit() : null,
            'on_same_date'         => $this->faker->boolean(50) ? 'Must coordinate screen graphics on show night same date.' : null,
            'cardholder_times'     => 'VIP Doors: 5:30 PM, Public Doors: 6:30 PM',
            'logos'                => 'Use standard primary corporate logo. Do not use dark variants.',
            'special_instructions' => $this->faker->paragraph(1),
        ];
    }
}