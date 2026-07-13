<?php

use App\Enums\MatterReviewStatus;
use App\Models\Matter;
use App\Models\Resident;
use Laravel\Sanctum\Sanctum;

test('a matter exposes its raw payload to the initiator so they can edit it', function () {
    $owner = Resident::factory()->create(['unit_label' => '1栋']);
    Sanctum::actingAs($owner);

    $census = Matter::factory()->create([
        'type' => 'census',
        'state' => 'open',
        'initiator_id' => $owner->id,
        'payload' => ['purpose' => '开团前摸底', 'modules' => [['key' => 'm1', 'title' => '预算', 'questions' => []]]],
    ]);

    $this->getJson('/api/matters/'.$census->id)
        ->assertSuccessful()
        ->assertJsonPath('data.payload.purpose', '开团前摸底')
        ->assertJsonPath('data.payload.modules.0.title', '预算');
});

test('a non-initiator member does not receive the raw payload', function () {
    $census = Matter::factory()->create([
        'type' => 'census',
        'state' => 'open',
        'payload' => ['purpose' => '开团前摸底', 'modules' => []],
    ]);

    Sanctum::actingAs(Resident::factory()->create());

    $this->getJson('/api/matters/'.$census->id)
        ->assertSuccessful()
        ->assertJsonMissingPath('data.payload');
});

test('an initiator editing an approved matter sends it back for review', function () {
    $initiator = Resident::factory()->create(['unit_label' => '3栋']);
    $matter = Matter::factory()->for($initiator, 'initiator')->create();
    Sanctum::actingAs($initiator);

    $this->putJson('/api/matters/'.$matter->id, [
        'title' => '改了标题的团购',
        'category' => '中央空调',
        'target_count' => 20,
    ])->assertSuccessful();

    expect($matter->refresh()->review_status)->toBe(MatterReviewStatus::Pending);
});

test('an admin editing an approved matter also sends it back for review', function () {
    $matter = Matter::factory()->create();
    Sanctum::actingAs(Resident::factory()->admin()->create());

    // 前端管理员编辑会带上 is_approved=true（取自当前已公示状态），仍应打回重审
    $this->putJson('/api/matters/'.$matter->id, [
        'title' => '管理员改的标题',
        'category' => '中央空调',
        'target_count' => 20,
        'is_approved' => true,
    ])->assertSuccessful();

    expect($matter->refresh()->review_status)->toBe(MatterReviewStatus::Pending);
});

test('an admin approves a pending matter via the publish toggle', function () {
    $matter = Matter::factory()->create(['review_status' => MatterReviewStatus::Pending]);
    Sanctum::actingAs(Resident::factory()->admin()->create());

    $this->putJson('/api/matters/'.$matter->id, [
        'title' => '待审的团购',
        'category' => '中央空调',
        'target_count' => 20,
        'is_approved' => true,
    ])->assertSuccessful();

    expect($matter->refresh()->review_status)->toBe(MatterReviewStatus::Approved);
});
