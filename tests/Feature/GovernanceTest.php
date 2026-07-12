<?php

use App\Enums\MatterReviewStatus;
use App\Models\Matter;
use App\Models\Party;
use App\Models\Resident;
use Laravel\Sanctum\Sanctum;

test('rejecting a matter stores the reason and editing resubmits it', function () {
    $initiator = Resident::factory()->create();
    $matter = Matter::factory()->pending()->for($initiator, 'initiator')->create();

    Sanctum::actingAs(Resident::factory()->admin()->create());
    $this->putJson("/api/admin/matters/{$matter->id}/approve", [
        'is_approved' => false,
        'reason' => '标题里带上品类，邻居才知道团什么',
    ])->assertSuccessful();

    // 发起人在详情页能看到驳回理由
    Sanctum::actingAs($initiator);
    $this->getJson("/api/matters/{$matter->id}")
        ->assertJsonPath('data.reject_reason', '标题里带上品类，邻居才知道团什么');

    // 修改后保存即重新提交：理由清空、仍待审核
    $this->putJson("/api/matters/{$matter->id}", [
        'title' => '断桥铝门窗团购',
        'category' => '门窗',
        'target_count' => 20,
    ])->assertSuccessful();

    $matter->refresh();
    expect($matter->reject_reason)->toBe('')
        ->and($matter->is_approved)->toBeFalse()
        ->and($matter->review_status)->toBe(MatterReviewStatus::Pending);
});

test('approving clears any previous reject reason', function () {
    $matter = Matter::factory()->rejected('旧理由')->create();
    Sanctum::actingAs(Resident::factory()->admin()->create());

    $this->putJson("/api/admin/matters/{$matter->id}/approve", ['is_approved' => true])->assertSuccessful();

    expect($matter->refresh()->reject_reason)->toBe('');
});

test('certified governance parties post official responses onto the timeline', function () {
    $matter = Matter::factory()->rights()->create();
    $property = Party::factory()->listed()->create(['type' => Party::TYPE_PROPERTY, 'name' => '天青府物业服务中心']);
    $manager = Resident::factory()->create(['affiliated_party_id' => $property->id]);
    Sanctum::actingAs($manager);

    $this->postJson("/api/matters/{$matter->id}/updates", [
        'happened_on' => '2026-07-11',
        'content' => '已收到联名函，下周三前书面答复',
    ])->assertCreated();

    $this->getJson("/api/matters/{$matter->id}")
        ->assertJsonPath('data.updates.0.author', '物业 · 天青府物业服务中心')
        ->assertJsonPath('data.updates.0.content', '已收到联名函，下周三前书面答复');
});

test('uncertified parties, merchants and bystanders cannot post responses', function (callable $makeResident) {
    $matter = Matter::factory()->rights()->create();
    Sanctum::actingAs($makeResident());

    $this->postJson("/api/matters/{$matter->id}/updates", [
        'happened_on' => '2026-07-11',
        'content' => '我来说两句',
    ])->assertForbidden();
})->with([
    '未认证的物业' => [fn () => Resident::factory()->create([
        'affiliated_party_id' => Party::factory()->create(['type' => Party::TYPE_PROPERTY])->id,
    ])],
    '已认证的商家' => [fn () => Resident::factory()->create([
        'affiliated_party_id' => Party::factory()->listed()->merchant()->create()->id,
    ])],
    '普通邻居' => [fn () => Resident::factory()->create()],
]);

test('the initiator posts progress as themselves even when affiliated to a party', function () {
    $property = Party::factory()->listed()->create(['type' => Party::TYPE_PROPERTY]);
    $initiator = Resident::factory()->create(['affiliated_party_id' => $property->id]);
    $matter = Matter::factory()->rights()->for($initiator, 'initiator')->create();
    Sanctum::actingAs($initiator);

    $this->postJson("/api/matters/{$matter->id}/updates", [
        'happened_on' => '2026-07-11',
        'content' => '联名函已递交',
    ])->assertCreated();

    $this->getJson("/api/matters/{$matter->id}")
        ->assertJsonPath('data.updates.0.author', null);
});
