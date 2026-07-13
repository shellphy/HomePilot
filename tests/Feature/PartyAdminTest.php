<?php

use App\Models\Matter;
use App\Models\Party;
use App\Models\Resident;
use App\Settings\CommunitySettings;
use Laravel\Sanctum\Sanctum;

test('admin rejects a party with a reason and it leaves the pending queue', function () {
    $party = Party::factory()->create(['name' => '青城中央空调']);
    Resident::factory()->create(['affiliated_party_id' => $party->id, 'phone' => '13800138000']);
    Sanctum::actingAs(Resident::factory()->admin()->create());

    $this->putJson("/api/admin/parties/{$party->id}", ['is_approved' => false, 'reason' => '资料不完整'])
        ->assertSuccessful()
        ->assertJsonPath('data.review_status', 'rejected')
        ->assertJsonPath('data.reject_reason', '资料不完整');

    expect($party->refresh()->is_listed)->toBeFalse();

    // 驳回态在等归属人改，不再占核验队列的待办
    $this->getJson('/api/admin/parties')->assertJsonPath('pending_count', 0);
});

test('certifying a party is admin only', function () {
    $party = Party::factory()->create();
    Sanctum::actingAs(Resident::factory()->create());

    $this->putJson("/api/admin/parties/{$party->id}", ['is_approved' => true])->assertForbidden();
});

test('a self registered property member can post official responses once certified', function () {
    // 物业和商家走同一条链路：自助亮明身份 → 管理员核验
    $member = Resident::factory()->create();
    Sanctum::actingAs($member);
    $this->postJson('/api/me/party', ['type' => 'property', 'name' => '天青府物业服务中心'])
        ->assertSuccessful();

    $matter = Matter::factory()->rights()->create();

    // 核验前不能官方回应
    $this->postJson("/api/matters/{$matter->id}/updates", [
        'happened_on' => '2026-07-11',
        'content' => '已收到联名诉求。',
    ])->assertForbidden();

    Sanctum::actingAs(Resident::factory()->admin()->create());
    $partyId = $member->refresh()->affiliated_party_id;
    $this->putJson("/api/admin/parties/{$partyId}", ['is_approved' => true])->assertSuccessful();

    // 用 fresh 实例重新登录，避免核验前缓存的 affiliatedParty 关系
    Sanctum::actingAs($member->fresh());
    $this->postJson("/api/matters/{$matter->id}/updates", [
        'happened_on' => '2026-07-11',
        'content' => '已收到联名诉求，本周五前给出书面答复。',
    ])->assertCreated()->assertJsonPath('data.author', '物业 · 天青府物业服务中心');
});

test('the pending count tracks unlisted parties that have members', function () {
    Sanctum::actingAs(Resident::factory()->admin()->create());
    Party::factory()->create(); // 空壳档案不算待办
    Party::factory()->listed()->create();
    Resident::factory()->merchant()->create(); // merchant() 绑定一个未核验商家

    $this->getJson('/api/admin/parties')
        ->assertSuccessful()
        ->assertJsonPath('pending_count', 1);
});

test('options carry the per type form metadata for the registration page', function () {
    $this->getJson('/api/options')
        ->assertSuccessful()
        ->assertJsonPath('party_types.0.key', 'merchant')
        ->assertJsonPath('party_types.0.category_label', '主营')
        ->assertJsonPath('party_types.1.key', 'property')
        ->assertJsonPath('party_types.1.self_registrable', true)
        ->assertJsonPath('party_types.1.category_label', '');
});

test('options expose the admin contact for the certification guide', function () {
    $this->getJson('/api/options')
        ->assertSuccessful()
        ->assertJsonPath('community.admin_contact', app(CommunitySettings::class)->admin_contact);
});

test('admins can update the admin contact in settings', function () {
    Sanctum::actingAs(Resident::factory()->admin()->create());
    $payload = array_merge(app(CommunitySettings::class)->toArray(), [
        'admin_contact' => '微信 tqf-admin，或到 8 栋物业前台',
    ]);

    $this->putJson('/api/admin/settings', $payload)
        ->assertSuccessful()
        ->assertJsonPath('data.admin_contact', '微信 tqf-admin，或到 8 栋物业前台');

    $this->getJson('/api/options')
        ->assertJsonPath('community.admin_contact', '微信 tqf-admin，或到 8 栋物业前台');
});
