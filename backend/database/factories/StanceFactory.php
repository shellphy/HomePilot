<?php

namespace Database\Factories;

use App\Models\Matter;
use App\Models\Resident;
use App\Models\Stance;
use App\Settings\CommunitySettings;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stance>
 */
class StanceFactory extends Factory
{
    /**
     * 默认：一条接龙表态。
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'matter_id' => Matter::factory(),
            'resident_id' => Resident::factory(),
            'mode' => Stance::MODE_JOIN,
            'payload' => null,
        ];
    }

    public function review(int $rating = 5, string $content = ''): static
    {
        return $this->state(fn (): array => [
            'mode' => Stance::MODE_REVIEW,
            'payload' => ['rating' => $rating, 'content' => $content],
        ]);
    }

    /**
     * 征集表态（答案挂在所属征集事项上）。
     */
    public function censusAnswers(): static
    {
        return $this->state(fn (): array => [
            'mode' => Stance::MODE_REGISTER,
            'payload' => [
                'answers' => [
                    'layout' => fake()->randomElement(app(CommunitySettings::class)->layouts),
                    'decoration_mode' => fake()->randomElement(app(CommunitySettings::class)->decoration_modes),
                    'interests' => [fake()->randomElement(app(CommunitySettings::class)->categories)],
                ],
            ],
        ]);
    }
}
