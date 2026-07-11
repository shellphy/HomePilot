<?php

use App\Models\Matter;
use App\Models\Party;
use App\Models\Resident;
use App\Models\Stance;
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

test('party members answer censuses but stay out of rosters', function () {
    $matter = Matter::factory()->open()->create();
    $merchant = Resident::factory()->merchant()->create();
    Sanctum::actingAs($merchant);

    // 接龙名单是业主的信任背书：相关方不进名单；征集答题不受影响
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

    $this->postJson("/api/matters/{$matter->id}/join")->assertForbidden();
    $this->putJson("/api/matters/{$census->id}/census", ['answers' => ['q1' => '半包']])->assertCreated();
});

test('only certified merchants initiate, and only groupbuys or activities', function () {
    // 未认证商家：发起被拒，提示先认证
    Sanctum::actingAs(Resident::factory()->merchant()->create());
    $this->postJson('/api/matters', [
        'type' => 'groupbuy', 'category' => '门窗', 'title' => '商家直供团', 'target_count' => 10,
    ])->assertForbidden();

    // 已认证商家：可发团购（署商家名、带认证标识），不能发维权
    $certified = Resident::factory()->create([
        'affiliated_party_id' => Party::factory()->listed()->merchant()->create(['name' => '青城门窗'])->id,
    ]);
    Sanctum::actingAs($certified);

    $response = $this->postJson('/api/matters', [
        'type' => 'groupbuy', 'category' => '门窗', 'title' => '商家直供团', 'target_count' => 10,
    ])->assertCreated();

    expect($response->json('data.initiator_party.name'))->toBe('青城门窗')
        ->and($response->json('data.initiator_party.is_listed'))->toBeTrue();

    $this->postJson('/api/matters', ['type' => 'rights', 'title' => '商家来维权'])->assertForbidden();

    // 治理类相关方成员不发起事项
    Sanctum::actingAs(Resident::factory()->create([
        'affiliated_party_id' => Party::factory()->listed()->create(['type' => Party::TYPE_PROPERTY])->id,
    ]));
    $this->postJson('/api/matters', [
        'type' => 'groupbuy', 'category' => '门窗', 'title' => '物业发团', 'target_count' => 10,
    ])->assertForbidden();
});

test('merchant initiated matters keep the merchant byline after identity switches', function () {
    $party = Party::factory()->listed()->merchant()->create(['name' => '青城门窗']);
    $merchant = Resident::factory()->create(['affiliated_party_id' => $party->id]);
    Sanctum::actingAs($merchant);

    $matterId = $this->postJson('/api/matters', [
        'type' => 'groupbuy', 'category' => '门窗', 'title' => '商家直供团', 'target_count' => 10,
    ])->json('data.id');

    // 切回业主后，历史事项仍署发起时的商家身份（快照）
    $this->deleteJson('/api/me/party')->assertSuccessful();

    $this->getJson("/api/matters/{$matterId}")
        ->assertJsonPath('data.initiator_party.name', '青城门窗')
        ->assertJsonPath('data.initiator_name', '青城门窗');
});

test('the certified party directory ships deal counts and review ratings', function () {
    $listed = Party::factory()->listed()->merchant()->create(['name' => '青城门窗', 'category' => '门窗']);
    Party::factory()->merchant()->create(); // 未认证：不进名录

    $initiator = Resident::factory()->create(['affiliated_party_id' => $listed->id]);
    $deal = Matter::factory()->done()->for($initiator, 'initiator')->create(['initiator_party_id' => $listed->id]);
    Stance::factory()->review(5, '手艺不错')->for($deal, 'matter')->create();
    Stance::factory()->review(4)->for($deal, 'matter')->create();

    Sanctum::actingAs(Resident::factory()->create());

    $this->getJson('/api/parties')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', '青城门窗')
        ->assertJsonPath('data.0.deal_count', 1)
        ->assertJsonPath('data.0.review_count', 2)
        ->assertJsonPath('data.0.rating', 4.5);
});
