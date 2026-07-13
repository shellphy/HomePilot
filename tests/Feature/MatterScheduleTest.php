<?php

use App\Models\Matter;
use App\Models\Resident;
use Laravel\Sanctum\Sanctum;

test('activities expose structured schedule fields and stop accepting joins after the deadline', function () {
    $matter = Matter::factory()->activity()->create([
        'starts_at' => now()->addDay(),
        'registration_deadline_at' => now()->subMinute(),
        'location' => '北门广场',
    ]);
    Sanctum::actingAs(Resident::factory()->create(['unit_label' => '3栋']));

    $this->getJson("/api/matters/{$matter->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.location', '北门广场')
        ->assertJsonPath('data.registration_closed', true)
        ->assertJsonPath('data.join_open', false);

    $this->postJson("/api/matters/{$matter->id}/join")->assertUnprocessable();
});

test('a groupbuy accepts a signup window that closes after its start time or has no start time', function () {
    Sanctum::actingAs(Resident::factory()->inUnit('3栋')->create());

    // 团购报名窗口本就常晚于开始时间，且开始时间可留空——都应能提交
    $this->postJson('/api/matters', [
        'type' => 'groupbuy',
        'title' => '全屋定制团',
        'category' => '家具',
        'target_count' => 10,
        'starts_at' => now()->addDay()->format('Y-m-d H:i'),
        'registration_deadline_at' => now()->addWeek()->format('Y-m-d H:i'),
    ])->assertCreated();

    $this->postJson('/api/matters', [
        'type' => 'groupbuy',
        'title' => '临期食品团',
        'category' => '食品',
        'target_count' => 5,
        'registration_deadline_at' => now()->addWeek()->format('Y-m-d H:i'),
    ])->assertCreated();
});

test('the scheduler closes activities and aid after their start time', function () {
    $activity = Matter::factory()->activity()->create(['starts_at' => now()->subMinute()]);
    $aid = Matter::factory()->aid()->create(['starts_at' => now()->subMinute()]);
    $future = Matter::factory()->activity()->create(['starts_at' => now()->addHour()]);

    $this->artisan('matters:close-expired')->assertSuccessful();

    expect($activity->refresh()->state)->toBe('done')
        ->and($aid->refresh()->state)->toBe('closed')
        ->and($future->refresh()->state)->toBe('open');
});
