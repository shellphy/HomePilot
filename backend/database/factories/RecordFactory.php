<?php

namespace Database\Factories;

use App\Models\Matter;
use App\Models\Record;
use App\Models\Resident;
use App\Settings\CommunitySettings;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Record>
 */
class RecordFactory extends Factory
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
            'mode' => Record::MODE_JOIN,
            'subject' => '',
            'payload' => null,
        ];
    }

    public function review(int $rating = 5, string $content = ''): static
    {
        return $this->state(fn (): array => [
            'mode' => Record::MODE_REVIEW,
            'payload' => ['rating' => $rating, 'content' => $content],
        ]);
    }

    /**
     * 征集表态（答案挂在所属征集事务上）。
     */
    public function censusAnswers(): static
    {
        return $this->state(fn (): array => [
            'mode' => Record::MODE_REGISTER,
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
