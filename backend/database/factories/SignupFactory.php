<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Resident;
use App\Models\Signup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Signup>
 */
class SignupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'resident_id' => Resident::factory(),
        ];
    }
}
