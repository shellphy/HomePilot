<?php

use App\Models\Matter;
use App\Models\Party;
use App\Models\Resident;
use App\Models\Stance;
use Laravel\Sanctum\Sanctum;

test('editing an approved party profile sends it back for re-review', function () {
    $merchant = Resident::factory()->merchant()->create();
    $merchant->affiliatedParty->approve();
    Sanctum::actingAs($merchant);

    // 改了公开资料（主营）→ 打回待认证，重新过审前退出名录
    $this->postJson('/api/me/party', ['type' => 'merchant', 'name' => '青城中央空调', 'category' => '地暖'])
        ->assertSuccessful()
        ->assertJsonPath('data.party.review_status', 'pending')
        ->assertJsonPath('data.party.is_listed', false);
});

test('a rejected party returns to pending when the owner resubmits its profile', function () {
    $party = Party::factory()->rejected()->merchant()->create(['name' => '青城中央空调']);
    $member = Resident::factory()->create(['affiliated_party_id' => $party->id, 'last_party_id' => $party->id]);
    Sanctum::actingAs($member);

    $this->postJson('/api/me/party', ['type' => 'merchant', 'name' => '青城中央空调（已补充资料）'])
        ->assertSuccessful()
        ->assertJsonPath('data.party.review_status', 'pending')
        ->assertJsonPath('data.party.reject_reason', '');
});

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

test('governance identities self register the same way and carry no category', function (string $type, string $label) {
    Sanctum::actingAs(Resident::factory()->create());

    // 主营是商家专属的补充字段：其他类型即使提交也会被忽略
    $this->postJson('/api/me/party', ['type' => $type, 'name' => '某组织', 'category' => '物业服务'])
        ->assertSuccessful()
        ->assertJsonPath('data.party.label', $label)
        ->assertJsonPath('data.party.category', '');
})->with([
    ['property', '物业'],
    ['developer', '开发商'],
    ['committee', '业委会'],
]);

test('unknown party types are rejected', function () {
    Sanctum::actingAs(Resident::factory()->create());

    $this->postJson('/api/me/party', ['type' => 'union', 'name' => '某组织'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('type');
});

test('switching away and back restores the same party with profile and certification intact', function () {
    $merchant = Resident::factory()->merchant()->create();
    $merchant->affiliatedParty->approve();
    Sanctum::actingAs($merchant);

    $this->deleteJson('/api/me/party')
        ->assertSuccessful()
        ->assertJsonPath('data.party', null)
        // 档案没删：上次的资料随 /me 下发，资料页据此预填
        ->assertJsonPath('data.last_party.name', '青城中央空调');

    // 再次入驻同类型：找回原档案，认证状态原样保留，不产生新记录
    $this->postJson('/api/me/party', ['type' => 'merchant', 'name' => '青城中央空调', 'category' => '中央空调'])
        ->assertSuccessful()
        ->assertJsonPath('data.party.is_listed', true);

    expect(Party::count())->toBe(1);
});

test('flip-flopping identities does not stack pending records in the queue', function () {
    Sanctum::actingAs(Resident::factory()->create());

    $this->postJson('/api/me/party', ['type' => 'merchant', 'name' => '青城空调'])->assertSuccessful();
    $this->deleteJson('/api/me/party')->assertSuccessful();
    $this->postJson('/api/me/party', ['type' => 'merchant', 'name' => '青城空调'])->assertSuccessful();
    $this->deleteJson('/api/me/party')->assertSuccessful();
    $this->postJson('/api/me/party', ['type' => 'merchant', 'name' => '青城空调'])->assertSuccessful();

    expect(Party::count())->toBe(1);
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
    Party::factory()->listed()->create(['type' => Party::TYPE_PROPERTY]); // 治理身份：认证了也不进商家名录

    $initiator = Resident::factory()->create(['affiliated_party_id' => $listed->id]);
    $deal = Matter::factory()->done()->for($initiator, 'initiator')->create(['initiator_party_id' => $listed->id]);
    Stance::factory()->review(5, '手艺不错')->for($deal, 'matter')->create();
    Stance::factory()->review(4)->for($deal, 'matter')->create();

    Sanctum::actingAs(Resident::factory()->create());

    $this->getJson('/api/parties')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', '青城门窗')
        ->assertJsonPath('data.0.phone', $initiator->phone)
        ->assertJsonPath('data.0.deal_count', 1)
        ->assertJsonPath('data.0.review_count', 2)
        ->assertJsonPath('data.0.rating', 4.5);

    // 归属人暂时切回业主，名录里的联系电话不受影响
    Sanctum::actingAs($initiator);
    $this->deleteJson('/api/me/party')->assertSuccessful();

    $this->getJson('/api/parties')->assertJsonPath('data.0.phone', $initiator->phone);
});
