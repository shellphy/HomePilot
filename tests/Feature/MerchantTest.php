<?php

use App\Models\Matter;
use App\Models\Party;
use App\Models\Resident;
use Laravel\Sanctum\Sanctum;

test('a resident can self register as a merchant', function () {
    $resident = Resident::factory()->create();
    Sanctum::actingAs($resident);

    $this->postJson('/api/me/party', [
        'type' => 'merchant',
        'name' => '青城中央空调',
        'category' => '中央空调',
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.party.type', 'merchant')
        ->assertJsonPath('data.party.label', '商家')
        ->assertJsonPath('data.party.name', '青城中央空调');

    expect($resident->refresh()->affiliatedParty->is_listed)->toBeFalse(); // 公示名单要管理员认证
});

test('resubmitting merchant info updates the same party instead of creating another', function () {
    $resident = Resident::factory()->create();
    Sanctum::actingAs($resident);

    $this->postJson('/api/me/party', ['type' => 'merchant', 'name' => '青城空调', 'category' => '中央空调'])
        ->assertSuccessful();
    $this->postJson('/api/me/party', ['type' => 'merchant', 'name' => '青城暖通', 'category' => '地暖'])
        ->assertSuccessful()
        ->assertJsonPath('data.party.name', '青城暖通');

    expect(Party::count())->toBe(1);
});

test('types that are not open for self registration are rejected', function (string $type) {
    Sanctum::actingAs(Resident::factory()->create());

    $this->postJson('/api/me/party', ['type' => $type, 'name' => '某组织'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('type');
})->with(['property', 'developer', 'committee']);

test('a party member can switch back to resident and the party record survives', function () {
    $merchant = Resident::factory()->merchant()->create();
    Sanctum::actingAs($merchant);

    $this->deleteJson('/api/me/party')
        ->assertSuccessful()
        ->assertJsonPath('data.party', null);

    expect($merchant->refresh()->affiliated_party_id)->toBeNull()
        ->and(Party::count())->toBe(1); // 相关方档案保留，供管理端追溯
});

test('party members participate on equal footing: join, answer censuses, initiate matters', function () {
    $matter = Matter::factory()->open()->create();
    $merchant = Resident::factory()->merchant()->create();
    Sanctum::actingAs($merchant);

    // 身份只是亮明给大家看的信息，不是参与门槛：报名、答题、发起都和业主一样
    $census = Matter::factory()->create([
        'type' => 'census',
        'state' => 'open',
        'payload' => [
            'modules' => [[
                'key' => 'basic',
                'title' => '基础',
                'questions' => [['key' => 'q1', 'text' => '打算怎么装？', 'type' => 'single', 'options' => ['清包', '半包']]],
            ]],
        ],
    ]);

    $this->postJson("/api/matters/{$matter->id}/join")->assertCreated();
    $this->putJson("/api/matters/{$census->id}/census", ['answers' => ['q1' => '半包']])->assertCreated();
    $this->postJson('/api/matters', [
        'type' => 'groupbuy',
        'category' => '门窗',
        'title' => '商家自荐团',
        'target_count' => 10,
    ])->assertCreated();
});
