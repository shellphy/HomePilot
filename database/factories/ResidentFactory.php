<?php

namespace Database\Factories;

use App\Models\Party;
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
            'unionid' => fake()->unique()->md5(),
            'openid_mp' => fake()->unique()->md5(),
            'nickname' => fake()->firstName(),
            'unit_label' => fake()->numberBetween(1, 8).'栋',
            'layout_label' => fake()->randomElement(['107㎡', '130㎡', '154㎡']),
            'phone' => fake()->numerify('138########'),
        ];
    }

    /**
     * 指定楼栋。
     */
    public function inUnit(string $label): static
    {
        return $this->state(fn (): array => ['unit_label' => $label]);
    }

    /**
     * 尚未填写楼栋的新用户。
     */
    public function withoutUnit(): static
    {
        return $this->state(fn (): array => ['unit_label' => '']);
    }

    /**
     * 管理员（admin:grant 授权后的成员）。
     */
    public function admin(): static
    {
        return $this->state(fn (): array => ['is_admin' => true]);
    }

    public function superAdmin(): static
    {
        return $this->state(fn (): array => ['is_admin' => true, 'is_super_admin' => true]);
    }

    /** 被拉黑的成员。 */
    public function blocked(): static
    {
        return $this->state(fn (): array => ['blocked_at' => now()]);
    }

    /**
     * 商家身份（绑定一个 merchant 相关方）。
     */
    public function merchant(string $name = '青城中央空调', string $category = '中央空调'): static
    {
        return $this->state(fn (): array => [
            'affiliated_party_id' => Party::factory()->merchant()->create(['name' => $name, 'category' => $category])->id,
        ]);
    }
}
