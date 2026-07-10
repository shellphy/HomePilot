<?php

namespace Database\Factories;

use App\Models\ProgressUpdate;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProgressUpdate>
 */
class ProgressUpdateFactory extends Factory
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
            'happened_on' => fake()->dateTimeBetween('-1 month')->format('Y-m-d'),
            'content' => fake()->sentence(),
            'images' => [],
        ];
    }
}
