<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        return [
            // Generates a random realistic department name like "Marketing" or "Production"
            'name' => $this->faker->unique()->randomElement([
                'Broadcast Video', 
                'Social Video', 
                'Audio Creative', 
                'Key Art Design', 
                'Tour Operations'
            ]) . ' ' . $this->faker->randomNumber(3),
        ];
    }
}