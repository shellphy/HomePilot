<?php

use App\Models\Matter;
use App\Models\Resident;
use App\Models\Stance;
use Laravel\Sanctum\Sanctum;

// ---- 站内未读红点：事项动态 vs 已读时间，喂给「我的」页 ----

test('a rejection lights the mine badge for the initiator and marking seen clears it', function () {
    $initiator = Resident::factory()->create();
    $matter = Matter::factory()->pending()->for($initiator, 'initiator')->create();

    Sanctum::actingAs(Resident::factory()->admin()->create());
    $this->putJson("/api/admin/matters/{$matter->id}/approve", [
        'is_approved' => false,
        'reason' => '标题不清楚',
    ])->assertSuccessful();

    Sanctum::actingAs($initiator);
    $this->getJson('/api/me')->assertJsonPath('data.has_mine_updates', true);

    $this->postJson('/api/me/seen', ['kind' => 'mine'])
        ->assertSuccessful()
        ->assertJsonPath('data.has_mine_updates', false);

    $this->getJson('/api/me')->assertJsonPath('data.has_mine_updates', false);
});

test('an approval also lights the mine badge', function () {
    $initiator = Resident::factory()->create();
    $matter = Matter::factory()->pending()->for($initiator, 'initiator')->create();

    Sanctum::actingAs(Resident::factory()->admin()->create());
    $this->putJson("/api/admin/matters/{$matter->id}/approve", ['is_approved' => true])->assertSuccessful();

    Sanctum::actingAs($initiator);
    $this->getJson('/api/me')->assertJsonPath('data.has_mine_updates', true);
});

test('a state flip lights the joined badge for participants but not for the acting initiator', function () {
    $initiator = Resident::factory()->create();
    $participant = Resident::factory()->create();
    $matter = Matter::factory()->open()->for($initiator, 'initiator')->create();
    Stance::factory()->for($matter, 'matter')->for($participant, 'resident')->create();

    Sanctum::actingAs($initiator);
    $this->putJson("/api/matters/{$matter->id}/state", ['state' => 'done'])->assertSuccessful();

    // 自己触发的流转不给自己亮红点
    $this->getJson('/api/me')->assertJsonPath('data.has_mine_updates', false);

    Sanctum::actingAs($participant);
    $this->getJson('/api/me')->assertJsonPath('data.has_joined_updates', true);

    $this->postJson('/api/me/seen', ['kind' => 'joined'])->assertSuccessful();
    $this->getJson('/api/me')->assertJsonPath('data.has_joined_updates', false);
});

test('a timeline update and a deal publication light the joined badge again after it was seen', function () {
    $initiator = Resident::factory()->create();
    $participant = Resident::factory()->create();
    $matter = Matter::factory()->open()->for($initiator, 'initiator')->create();
    Stance::factory()->for($matter, 'matter')->for($participant, 'resident')->create();

    Sanctum::actingAs($participant);
    $this->postJson('/api/me/seen', ['kind' => 'joined'])->assertSuccessful();

    // 已读与新动态发生在同一秒会分不出先后，往后拨一分钟再产生动态
    $this->travel(1)->minutes();

    Sanctum::actingAs($initiator);
    $this->postJson("/api/matters/{$matter->id}/updates", [
        'happened_on' => now()->toDateString(),
        'content' => '和商家谈妥了首轮价格',
    ])->assertCreated();

    Sanctum::actingAs($participant);
    $this->getJson('/api/me')->assertJsonPath('data.has_joined_updates', true);
    $this->postJson('/api/me/seen', ['kind' => 'joined'])->assertSuccessful();

    $this->travel(1)->minutes();

    Sanctum::actingAs($initiator);
    $this->putJson("/api/matters/{$matter->id}/state", ['state' => 'done'])->assertSuccessful();
    $this->putJson("/api/matters/{$matter->id}/deal", [
        'final_terms' => [['label' => '成交价', 'value' => '299/㎡']],
    ])->assertSuccessful();

    Sanctum::actingAs($participant);
    $this->getJson('/api/me')->assertJsonPath('data.has_joined_updates', true);
});

test('mark seen rejects unknown kinds', function () {
    Sanctum::actingAs(Resident::factory()->create());

    $this->postJson('/api/me/seen', ['kind' => 'everything'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('kind');
});
