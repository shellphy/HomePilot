<?php

namespace Database\Factories;

use App\Models\Matter;
use App\Models\Resident;
use App\Models\Stance;
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

    /**
     * 登记意向档的接龙（团购条款未定前的兴趣，不算进成交名单）。
     */
    public function intent(bool $shareContact = true): static
    {
        return $this->state(fn (): array => [
            'payload' => ['share_contact' => $shareContact, 'stage' => Stance::JOIN_STAGE_INTENT],
        ]);
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
                    // 户型/装修方式与种子问卷（DatabaseSeeder 基础登记模块）的选项保持一致
                    'layout' => fake()->randomElement(['107㎡', '130㎡', '154㎡']),
                    'decoration_mode' => fake()->randomElement(['全包（都交给装修公司）', '半包（主材自己买）', '清包（只请工人）', '还没定']),
                    'interests' => [fake()->randomElement(['装修公司', '中央空调', '地暖', '门窗'])],
                ],
            ],
        ]);
    }
}
