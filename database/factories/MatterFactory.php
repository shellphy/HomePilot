<?php

namespace Database\Factories;

use App\Enums\MatterReviewStatus;
use App\Models\Matter;
use App\Models\Resident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Matter>
 */
class MatterFactory extends Factory
{
    /**
     * 默认：已审核、意向征集中的团购事项。
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'groupbuy',
            'initiator_id' => Resident::factory(),
            'title' => fake()->randomElement(['装修公司', '中央空调', '地暖', '门窗']).'团购',
            'category' => fake()->randomElement(['装修公司', '中央空调', '地暖', '门窗']),
            'state' => 'seeking',
            'review_status' => MatterReviewStatus::Approved,
            'target_count' => fake()->numberBetween(10, 40),
            'payload' => [
                'pitch' => fake()->sentence(),
                'perk' => '',
                'terms' => [],
                'glossary' => [],
            ],
        ];
    }

    public function negotiating(): static
    {
        return $this->state(fn (): array => ['state' => 'negotiating']);
    }

    public function open(): static
    {
        return $this->state(fn (): array => ['state' => 'open']);
    }

    public function done(): static
    {
        return $this->state(fn (): array => ['state' => 'done']);
    }

    public function aborted(): static
    {
        return $this->state(fn (): array => ['state' => 'aborted']);
    }

    /**
     * 方案型团购（中央空调这类非标品）：商家逐户沟通需求、单独出方案，联系互通提前到谈判中。
     */
    public function survey(): static
    {
        return $this->state(fn (array $attributes): array => [
            'payload' => array_merge($attributes['payload'] ?? [], ['needs_survey' => true]),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (): array => ['review_status' => MatterReviewStatus::Pending]);
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['review_status' => MatterReviewStatus::Draft]);
    }

    public function rejected(string $reason = '需要补充信息'): static
    {
        return $this->state(fn (): array => [
            'review_status' => MatterReviewStatus::Rejected,
            'reject_reason' => $reason,
        ]);
    }

    public function activity(): static
    {
        return $this->state(fn (): array => [
            'type' => 'activity',
            'state' => 'open',
            'category' => '',
            'payload' => ['pitch' => fake()->sentence()],
        ]);
    }

    public function aid(): static
    {
        return $this->state(fn (): array => [
            'type' => 'aid',
            'state' => 'open',
            'category' => '',
            'payload' => ['pitch' => fake()->sentence()],
        ]);
    }

    public function rights(): static
    {
        return $this->state(fn (): array => [
            'type' => 'rights',
            'state' => 'collecting',
            'category' => '',
            'payload' => ['pitch' => fake()->sentence()],
        ]);
    }

    /**
     * 公告事项（第二类型）。
     */
    public function notice(): static
    {
        return $this->state(fn (): array => [
            'type' => 'notice',
            'state' => 'published',
            'category' => '',
            'target_count' => 0,
            'payload' => ['body' => fake()->paragraph()],
        ]);
    }
}
