<?php

namespace Database\Factories;

use App\Models\Matter;
use App\Models\Resident;
use App\Settings\CommunitySettings;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Matter>
 */
class MatterFactory extends Factory
{
    /**
     * 默认：已审核、意向征集中的团购事务。
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'groupbuy',
            'initiator_id' => Resident::factory(),
            'title' => fake()->randomElement(app(CommunitySettings::class)->categories).'团购',
            'category' => fake()->randomElement(app(CommunitySettings::class)->categories),
            'state' => 'seeking',
            'is_approved' => true,
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

    public function pending(): static
    {
        return $this->state(fn (): array => ['is_approved' => false]);
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
     * 公告事务（第二类型）。
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
