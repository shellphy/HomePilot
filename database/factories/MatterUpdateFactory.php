<?php

namespace Database\Factories;

use App\Models\Matter;
use App\Models\MatterUpdate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MatterUpdate>
 */
class MatterUpdateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'matter_id' => Matter::factory(),
            'happened_on' => fake()->dateTimeBetween('-1 month')->format('Y-m-d'),
            'content' => fake()->sentence(),
            'images' => [],
        ];
    }
}
