<?php

namespace Database\Factories;

use App\Models\Party;
use App\Settings\CommunitySettings;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Party>
 */
class PartyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => Party::TYPE_MERCHANT,
            'name' => fake()->company(),
            'category' => fake()->randomElement(app(CommunitySettings::class)->categories),
            'is_listed' => false,
        ];
    }

    public function merchant(): static
    {
        return $this->state(fn (): array => ['type' => Party::TYPE_MERCHANT]);
    }

    public function listed(): static
    {
        return $this->state(fn (): array => ['is_listed' => true]);
    }
}
