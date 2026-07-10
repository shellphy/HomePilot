<?php

namespace Database\Factories;

use App\Models\Resident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Resident>
 */
class ResidentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'openid' => fake()->unique()->md5(),
            'nickname' => fake()->firstName(),
            'unit_label' => fake()->numberBetween(1, 8).'栋',
            'phone' => fake()->numerify('138########'),
            'wechat_id' => fake()->userName(),
        ];
    }
}
