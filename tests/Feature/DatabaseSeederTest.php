<?php

use App\Enums\MatterReviewStatus;
use App\Models\Matter;
use App\Models\Resident;
use App\Models\Stance;
use Database\Seeders\DatabaseSeeder;

test('it requires the first user to register before publishing initial content', function () {
    expect(fn () => $this->seed())->toThrow(
        RuntimeException::class,
        '请先注册首位用户，再执行初始内容 Seeder。',
    );

    expect(Matter::query()->count())->toBe(0);
});

test('it publishes only censuses as the first registered user without demo responses', function () {
    $firstResident = Resident::factory()->create(['nickname' => '首位用户']);
    Resident::factory()->create(['nickname' => '后来用户']);

    $this->seed();
    $this->seed();

    $matters = Matter::query()->get();
    $groupbuyDraft = app(DatabaseSeeder::class)->decorationGroupbuyDraft();

    expect($matters)->toHaveCount(5)
        ->and($matters->pluck('type')->unique()->all())->toBe(['census'])
        ->and($matters->pluck('initiator_id')->unique()->all())->toBe([$firstResident->id])
        ->and($matters->pluck('review_status')->unique()->all())->toBe([MatterReviewStatus::Approved])
        ->and($matters->pluck('state')->unique()->all())->toBe(['open'])
        ->and($matters->pluck('title'))->not->toContain('武汉拜斯达装饰 · 硬装全包意向团购')
        ->and($groupbuyDraft['title'])->toBe('武汉拜斯达装饰 · 硬装全包意向团购')
        ->and(Matter::query()->count())->toBe(5)
        ->and(Resident::query()->count())->toBe(2)
        ->and(Stance::query()->count())->toBe(0);
});
