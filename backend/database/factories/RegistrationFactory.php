<?php

namespace Database\Factories;

use App\Models\Registration;
use App\Models\Resident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Registration>
 */
class RegistrationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'resident_id' => Resident::factory(),
            'layout' => fake()->randomElement(config('homepilot.layouts')),
            'decoration_mode' => fake()->randomElement(config('homepilot.decoration_modes')),
            'interests' => fake()->randomElements(config('homepilot.categories'), fake()->numberBetween(1, 4)),
        ];
    }
}
