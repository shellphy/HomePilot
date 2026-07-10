<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $category = fake()->randomElement(config('homepilot.categories'));

        return [
            'category' => $category,
            'title' => $category.'团购',
            'status' => ProjectStatus::Seeking,
            'is_approved' => true,
            'target_households' => fake()->numberBetween(10, 30),
            'pitch' => fake()->sentence(),
            'terms' => [
                ['label' => '团购价', 'value' => fake()->numberBetween(500, 1200).' 元/㎡'],
            ],
            'perk' => '',
            'glossary' => [
                ['term' => fake()->word(), 'explain' => fake()->sentence()],
            ],
        ];
    }

    public function pending(): static
    {
        return $this->state(['is_approved' => false]);
    }

    public function negotiating(): static
    {
        return $this->state(['status' => ProjectStatus::Negotiating]);
    }

    public function open(): static
    {
        return $this->state(['status' => ProjectStatus::Open]);
    }

    public function done(): static
    {
        return $this->state(['status' => ProjectStatus::Done]);
    }
}
