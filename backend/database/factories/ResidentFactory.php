<?php

namespace Database\Factories;

use App\Models\Party;
use App\Models\Resident;
use App\Models\Unit;
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
            'openid' => fake()->unique()->md5(),
            'nickname' => fake()->firstName(),
            // 复用式取栋：同一栋住很多户，也避免与显式创建的栋号唯一键冲突
            'unit_id' => fn (): int => Unit::firstOrCreate(['label' => fake()->numberBetween(1, 20).'栋'])->id,
            'phone' => fake()->numerify('138########'),
            'wechat_id' => fake()->userName(),
        ];
    }

    /**
     * 指定楼栋（不存在则创建）。
     */
    public function inUnit(string $label): static
    {
        return $this->state(fn (): array => [
            'unit_id' => Unit::firstOrCreate(['label' => $label])->id,
        ]);
    }

    /**
     * 尚未绑定楼栋的新用户。
     */
    public function withoutUnit(): static
    {
        return $this->state(fn (): array => ['unit_id' => null]);
    }

    /**
     * 管理员（admin:grant 授权后的成员）。
     */
    public function admin(): static
    {
        return $this->state(fn (): array => ['is_admin' => true]);
    }

    /**
     * 商家身份（绑定一个 merchant 相关方）。
     */
    public function merchant(string $name = '青城中央空调', string $category = '中央空调'): static
    {
        return $this->state(fn (): array => [
            'party_id' => Party::factory()->merchant()->create(['name' => $name, 'category' => $category])->id,
        ]);
    }
}
