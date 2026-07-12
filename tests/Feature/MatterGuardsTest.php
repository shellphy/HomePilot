<?php

use App\Models\Matter;
use App\Models\Resident;
use App\Models\Stance;
use Illuminate\Database\UniqueConstraintViolationException;
use Laravel\Sanctum\Sanctum;

// ---- 发起前置：牵头人也要上「楼栋 + 昵称」的公示名单 ----

test('an owner without a unit label cannot initiate and gets a structured profile error', function () {
    Sanctum::actingAs(Resident::factory()->withoutUnit()->create());

    $this->postJson('/api/matters', [
        'type' => 'activity',
        'title' => '周六建材市场组团踩点',
        'pitch' => '早上九点小区北门集合',
    ])->assertUnprocessable()->assertJsonValidationErrors('profile');
});

test('an owner with a unit label initiates normally', function () {
    Sanctum::actingAs(Resident::factory()->inUnit('3栋')->create());

    $this->postJson('/api/matters', [
        'type' => 'activity',
        'title' => '周六建材市场组团踩点',
        'pitch' => '早上九点小区北门集合',
    ])->assertCreated();
});

// ---- 终态锁：已成团/已有结果后发起人不能再回退 ----

test('the initiator cannot flip a matter out of its final state', function () {
    $initiator = Resident::factory()->create();
    $matter = Matter::factory()->done()->for($initiator, 'initiator')->create();
    Sanctum::actingAs($initiator);

    $this->putJson("/api/matters/{$matter->id}/state", ['state' => 'open'])->assertUnprocessable();

    $this->putJson("/api/matters/{$matter->id}", [
        'title' => $matter->title,
        'category' => $matter->category,
        'target_count' => $matter->target_count,
        'state' => 'seeking',
    ])->assertUnprocessable();

    expect($matter->refresh()->state)->toBe('done');
});

// ---- 顺序流转：发起人只能沿状态机推进一步，跳步/回退都不行 ----

test('the initiator advances the state one step at a time', function () {
    $initiator = Resident::factory()->create();
    $matter = Matter::factory()->for($initiator, 'initiator')->create();
    Sanctum::actingAs($initiator);

    $this->putJson("/api/matters/{$matter->id}/state", ['state' => 'negotiating'])->assertSuccessful();
    $this->putJson("/api/matters/{$matter->id}/state", ['state' => 'open'])->assertSuccessful();
    $this->putJson("/api/matters/{$matter->id}/state", ['state' => 'done'])->assertSuccessful();

    expect($matter->refresh()->state)->toBe('done');
});

test('the initiator cannot skip states or move backwards', function () {
    $initiator = Resident::factory()->create();
    $matter = Matter::factory()->for($initiator, 'initiator')->create();
    Sanctum::actingAs($initiator);

    // seeking 一步跳 done：跳步会直接触发联系互通与评价，拦下
    $this->putJson("/api/matters/{$matter->id}/state", ['state' => 'done'])->assertUnprocessable();
    // seeking 跳 open 同样是跳步
    $this->putJson("/api/matters/{$matter->id}/state", ['state' => 'open'])->assertUnprocessable();

    $open = Matter::factory()->open()->for($initiator, 'initiator')->create();
    // open 回退 seeking：非终态之间也不允许回退
    $this->putJson("/api/matters/{$open->id}/state", ['state' => 'seeking'])->assertUnprocessable();
    // 编辑接口带 state 同样受守卫
    $this->putJson("/api/matters/{$open->id}", [
        'title' => $open->title,
        'category' => $open->category,
        'target_count' => $open->target_count,
        'state' => 'seeking',
    ])->assertUnprocessable();

    expect($matter->refresh()->state)->toBe('seeking')
        ->and($open->refresh()->state)->toBe('open');
});

test('admins can still rescue a matter out of its final state', function () {
    $matter = Matter::factory()->done()->create();
    Sanctum::actingAs(Resident::factory()->admin()->create());

    $this->putJson("/api/matters/{$matter->id}", [
        'title' => $matter->title,
        'category' => $matter->category,
        'target_count' => $matter->target_count,
        'state' => 'open',
    ])->assertSuccessful();

    expect($matter->refresh()->state)->toBe('open');
});

// ---- 软删除：误删可恢复，表态一并保留 ----

test('admin deletion soft deletes and keeps stances while hiding the matter from members', function () {
    $matter = Matter::factory()->create();
    Stance::factory()->for($matter, 'matter')->create();

    Sanctum::actingAs(Resident::factory()->admin()->create());
    $this->deleteJson("/api/matters/{$matter->id}")->assertSuccessful();

    expect(Matter::count())->toBe(0)
        ->and(Matter::withTrashed()->count())->toBe(1)
        ->and(Stance::count())->toBe(1);

    Sanctum::actingAs(Resident::factory()->create());
    $this->getJson("/api/matters/{$matter->id}")->assertNotFound();
    $this->getJson('/api/matters')->assertJsonCount(0, 'data');
});

// ---- 接龙共享意愿：变更走修订链 ----

test('changing share consent on a join keeps the revision trail', function () {
    $matter = Matter::factory()->open()->create();
    Sanctum::actingAs(Resident::factory()->create());

    $this->postJson("/api/matters/{$matter->id}/join", ['share_contact' => true])->assertCreated();
    $this->postJson("/api/matters/{$matter->id}/join", ['share_contact' => false])->assertCreated();

    $join = Stance::where('mode', Stance::MODE_JOIN)->first();

    expect(Stance::where('mode', Stance::MODE_JOIN)->count())->toBe(1)
        ->and($join->payload['share_contact'])->toBeFalse()
        ->and($join->revisions()->count())->toBe(1)
        ->and($join->revisions()->first()->payload['share_contact'])->toBeTrue();

    // 意愿没变的重复报名不产生新修订
    $this->postJson("/api/matters/{$matter->id}/join", ['share_contact' => false])->assertCreated();
    expect($join->revisions()->count())->toBe(1);
});

// ---- 评价扩展：活动/互助结束后开放，维权不开放 ----

test('participants can review an activity after it ends', function () {
    $matter = Matter::factory()->activity()->create(['state' => 'done']);
    $resident = Resident::factory()->create();
    Stance::factory()->for($matter, 'matter')->for($resident, 'resident')->create();
    Sanctum::actingAs($resident);

    $this->putJson("/api/matters/{$matter->id}/review", ['rating' => 5, 'content' => '组织得很好'])
        ->assertCreated();
});

test('participants can review an aid after it closes', function () {
    $matter = Matter::factory()->aid()->create(['state' => 'closed']);
    $resident = Resident::factory()->create();
    Stance::factory()->for($matter, 'matter')->for($resident, 'resident')->create();
    Sanctum::actingAs($resident);

    $this->putJson("/api/matters/{$matter->id}/review", ['rating' => 4])->assertCreated();
});

test('reviews stay closed while an activity is running and for rights matters entirely', function () {
    $running = Matter::factory()->activity()->create();
    $resident = Resident::factory()->create();
    Stance::factory()->for($running, 'matter')->for($resident, 'resident')->create();
    Sanctum::actingAs($resident);
    $this->putJson("/api/matters/{$running->id}/review", ['rating' => 5])->assertUnprocessable();

    $resolved = Matter::factory()->rights()->create(['state' => 'resolved']);
    Stance::factory()->for($resolved, 'matter')->for($resident, 'resident')->create();
    $this->putJson("/api/matters/{$resolved->id}/review", ['rating' => 5])->assertUnprocessable();
});

test('the matter detail exposes join and review phase flags', function () {
    Sanctum::actingAs(Resident::factory()->create());

    $done = Matter::factory()->done()->create();
    $this->getJson("/api/matters/{$done->id}")
        ->assertJsonPath('data.join_open', false)
        ->assertJsonPath('data.review_open', true);

    $open = Matter::factory()->activity()->create();
    $this->getJson("/api/matters/{$open->id}")
        ->assertJsonPath('data.join_open', true)
        ->assertJsonPath('data.review_open', false);
});

// ---- 一事一户一份：数据库唯一索引兜底 ----

test('duplicate stances of the same mode are rejected by the database', function () {
    $matter = Matter::factory()->create();
    $resident = Resident::factory()->create();
    Stance::factory()->for($matter, 'matter')->for($resident, 'resident')->create();

    expect(fn () => Stance::factory()->for($matter, 'matter')->for($resident, 'resident')->create())
        ->toThrow(UniqueConstraintViolationException::class);
});
