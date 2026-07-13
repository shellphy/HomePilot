<?php

namespace Database\Factories;

use App\Enums\PartyReviewStatus;
use App\Models\Party;
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
            'category' => fake()->randomElement(['装修公司', '中央空调', '地暖', '门窗']),
            'review_status' => PartyReviewStatus::Pending,
        ];
    }

    public function merchant(): static
    {
        return $this->state(fn (): array => ['type' => Party::TYPE_MERCHANT]);
    }

    public function listed(): static
    {
        return $this->state(fn (): array => ['review_status' => PartyReviewStatus::Approved]);
    }

    public function rejected(string $reason = '资料不完整'): static
    {
        return $this->state(fn (): array => ['review_status' => PartyReviewStatus::Rejected, 'reject_reason' => $reason]);
    }
}
