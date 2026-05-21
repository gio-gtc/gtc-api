<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TourFactory extends Factory
{
    protected $model = Tour::class;

    public function definition(): array
    {
        // 1. Determine the conditional boolean state first
        $requireApproval = $this->faker->boolean(20);

        // 2. Lookup existing targeted data based on your specific requirements
        // Pull a representative strictly from Organisation 1
        $gtcRepId = User::where('organisation_id', 1)->inRandomOrder()->first()?->id 
            ?? User::factory();

        // Pull a voice over artist from an organisation that possesses type 3
        $voiceOverId = $this->faker->optional(0.7)->passthrough(
            User::whereHas('organisation.types', function ($query) {
                $query->where('organisation_types.id', 3);
            })->inRandomOrder()->first()?->id ?? User::factory()
        );

        // Pull a completely random seeded department
        $departmentId = Department::inRandomOrder()->first()?->id 
            ?? Department::factory();

        return [
            'name' => $this->faker->words(2, true) . ' Tour ' . $this->faker->year(),
            'start_date' => $this->faker->dateTimeBetween('now', '+6 months')->format('Y-m-d'),
            'expire_on_sale_now_cuts' => $this->faker->dateTimeBetween('+6 months', '+1 year')->format('Y-m-d'),
            
            // Relational targets
            'gtc_rep_id' => $gtcRepId,
            'voice_over_id' => $voiceOverId,
            'department_id' => $departmentId,

            // Booleans
            'hold_all_invoices' => $this->faker->boolean(20), // 20% chance true
            'live_on_ordering_system' => $this->faker->boolean(40),
            'require_client_approval' => $requireApproval,
            
            // 👇 Conditionality Engine matching your exact business rule
            'client_approval_email' => $requireApproval ? $this->faker->safeEmail() : null,
            
            // Optional textual fields with a 40% chance of being null
            'tour_sponsor' => $this->faker->optional(0.6)->company(),
            'special_instructions' => $this->faker->optional(0.6)->paragraph(),

            // 👇 Financial values matching decimal(10,2) between $50 and $2,500
            // Uses optional(0.6) so roughly 40% of records will return cleanly as null
            'tv_first_cut' => $this->faker->optional(0.6)->randomFloat(2, 50, 2500),
            'tv_second_cut' => $this->faker->optional(0.6)->randomFloat(2, 50, 2500),
            'radio_single_duration' => $this->faker->optional(0.6)->randomFloat(2, 50, 2500),
            'radio_dual_duration' => $this->faker->optional(0.6)->randomFloat(2, 50, 2500),
            'key_art' => $this->faker->optional(0.6)->randomFloat(2, 50, 2500),
        ];
    }
}
