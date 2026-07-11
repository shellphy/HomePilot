<?php

namespace Database\Factories;

use App\Models\Matter;
use App\Models\MatterQuestion;
use App\Models\Resident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MatterQuestion>
 */
class MatterQuestionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'matter_id' => Matter::factory(),
            'resident_id' => Resident::factory(),
            'content' => fake()->randomElement(['内机保修几年？', '吊顶后层高还剩多少？', '外机噪音大不大？']),
        ];
    }

    public function answered(string $answer = '以商家书面确认为准，合同里会写明。', string $answeredBy = '团长'): static
    {
        return $this->state(fn (): array => [
            'answer' => $answer,
            'answered_by' => $answeredBy,
            'answered_at' => now(),
        ]);
    }
}
