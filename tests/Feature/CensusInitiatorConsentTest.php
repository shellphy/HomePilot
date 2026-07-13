<?php

use App\Models\Matter;
use App\Models\Party;
use App\Models\Resident;
use App\Models\Stance;
use Laravel\Sanctum\Sanctum;

/**
 * 署名发起方的征集，参与者可主动勾选把匿名破例给发起者本人看。
 */
function signedCensus(array $overrides = []): Matter
{
    return Matter::factory()->create(array_merge([
        'type' => 'census',
        'state' => 'open',
        'category' => '装修',
        'title' => '电梯改造需求调研',
        'initiator_party_id' => Party::factory()->create(['type' => Party::TYPE_PROPERTY, 'name' => '天青府物业服务中心'])->id,
        'payload' => [
            'pitch' => '摸一摸大家的改造意愿',
            'collects_contact' => true,
            'modules' => [[
                'key' => 'basic',
                'title' => '基础登记',
                'questions' => [
                    ['key' => 'layout', 'text' => '你家是哪个户型？', 'type' => 'single', 'options' => ['107㎡', '130㎡', '154㎡']],
                    ['key' => 'interests', 'text' => '对哪些团购感兴趣？', 'type' => 'multi', 'options' => ['装修公司', '门窗', '地暖']],
                    ['key' => 'note', 'text' => '还有什么想说的？', 'type' => 'text'],
                ],
            ]],
        ],
    ], $overrides));
}

function consentAnswers(array $overrides = []): array
{
    return array_merge([
        'layout' => '107㎡',
        'interests' => ['装修公司', '门窗'],
        'note' => '希望早点开工',
    ], $overrides);
}

test('checking the box stores visible_to_initiator on my stance', function () {
    $census = signedCensus();
    Sanctum::actingAs(Resident::factory()->create());

    $this->putJson("/api/matters/{$census->id}/census", [
        'answers' => consentAnswers(),
        'visible_to_initiator' => true,
    ])->assertCreated();

    $stance = Stance::where('mode', Stance::MODE_REGISTER)->first();
    expect($stance->payload['visible_to_initiator'])->toBeTrue();
});

test('without the flag the registration stays anonymous by default', function () {
    $census = signedCensus();
    Sanctum::actingAs(Resident::factory()->create());

    $this->putJson("/api/matters/{$census->id}/census", ['answers' => consentAnswers()])
        ->assertCreated();

    $stance = Stance::where('mode', Stance::MODE_REGISTER)->first();
    expect($stance->payload['visible_to_initiator'] ?? false)->toBeFalse();
});

test('the consent flag survives a later module submission that omits it', function () {
    $census = signedCensus();
    Sanctum::actingAs(Resident::factory()->create());

    $this->putJson("/api/matters/{$census->id}/census", [
        'answers' => ['layout' => '107㎡', 'interests' => ['门窗']],
        'visible_to_initiator' => true,
    ])->assertCreated();

    // 后续提交不带 flag：沿用上次的勾选，不被清掉
    $this->putJson("/api/matters/{$census->id}/census", ['answers' => ['note' => '补一句']])
        ->assertSuccessful();

    $stance = Stance::where('mode', Stance::MODE_REGISTER)->first();
    expect($stance->payload['visible_to_initiator'])->toBeTrue()
        ->and($stance->payload['answers'])->toHaveKey('note');

    // 明确取消勾选：flag 被更新为 false
    $this->putJson("/api/matters/{$census->id}/census", [
        'answers' => ['note' => '算了'],
        'visible_to_initiator' => false,
    ])->assertSuccessful();
    expect($stance->refresh()->payload['visible_to_initiator'])->toBeFalse();
});

test('census show exposes is_initiator and my_visible_to_initiator', function () {
    $initiator = Resident::factory()->create();
    $census = signedCensus(['initiator_id' => $initiator->id]);

    Sanctum::actingAs($initiator);
    $this->putJson("/api/matters/{$census->id}/census", [
        'answers' => consentAnswers(),
        'visible_to_initiator' => true,
    ])->assertCreated();

    $this->getJson("/api/matters/{$census->id}/census")
        ->assertSuccessful()
        ->assertJsonPath('is_initiator', true)
        ->assertJsonPath('my_visible_to_initiator', true);

    // 别人看：既不是发起者，也没勾过
    Sanctum::actingAs(Resident::factory()->create());
    $this->getJson("/api/matters/{$census->id}/census")
        ->assertSuccessful()
        ->assertJsonPath('is_initiator', false)
        ->assertJsonPath('my_visible_to_initiator', false);
});

test('the initiator sees only consenting registrants with readable answers and phone', function () {
    $initiator = Resident::factory()->create();
    $census = signedCensus(['initiator_id' => $initiator->id]);

    // 勾选授权者
    $consenter = Resident::factory()->create(['unit_label' => '3栋', 'nickname' => '老K', 'phone' => '13800138000']);
    Sanctum::actingAs($consenter);
    $this->putJson("/api/matters/{$census->id}/census", [
        'answers' => consentAnswers(),
        'visible_to_initiator' => true,
    ])->assertCreated();

    // 没勾的人：只进匿名统计，不出现在明细
    Sanctum::actingAs(Resident::factory()->create());
    $this->putJson("/api/matters/{$census->id}/census", ['answers' => consentAnswers()])->assertCreated();

    Sanctum::actingAs($initiator);
    $response = $this->getJson("/api/matters/{$census->id}/census-consented")
        ->assertSuccessful()
        ->assertJsonCount(1, 'data');

    $response->assertJsonPath('data.0.name', '3栋 老K')
        ->assertJsonPath('data.0.phone', '13800138000')
        ->assertJsonPath('data.0.answers.0.question', '你家是哪个户型？')
        ->assertJsonPath('data.0.answers.0.answer', '107㎡')
        ->assertJsonPath('data.0.answers.1.question', '对哪些团购感兴趣？')
        ->assertJsonPath('data.0.answers.1.answer', '装修公司、门窗')
        ->assertJsonPath('data.0.answers.2.answer', '希望早点开工');
});

test('phone stays hidden when the census does not collect contact', function () {
    $initiator = Resident::factory()->create();
    $census = signedCensus(['initiator_id' => $initiator->id, 'payload' => [
        'pitch' => '',
        'collects_contact' => false,
        'modules' => [[
            'key' => 'basic',
            'title' => '基础登记',
            'questions' => [
                ['key' => 'layout', 'text' => '户型？', 'type' => 'single', 'options' => ['107㎡']],
            ],
        ]],
    ]]);

    Sanctum::actingAs(Resident::factory()->create(['phone' => '13800138000']));
    $this->putJson("/api/matters/{$census->id}/census", [
        'answers' => ['layout' => '107㎡'],
        'visible_to_initiator' => true,
    ])->assertCreated();

    Sanctum::actingAs($initiator);
    $this->getJson("/api/matters/{$census->id}/census-consented")
        ->assertSuccessful()
        ->assertJsonPath('data.0.phone', '');
});

test('non initiator non admin cannot view the consented list', function () {
    $initiator = Resident::factory()->create();
    $census = signedCensus(['initiator_id' => $initiator->id]);

    Sanctum::actingAs(Resident::factory()->create());
    $this->getJson("/api/matters/{$census->id}/census-consented")->assertForbidden();
});

test('admins may view the consented list too', function () {
    $census = signedCensus(['initiator_id' => Resident::factory()->create()->id]);

    Sanctum::actingAs(Resident::factory()->admin()->create());
    $this->getJson("/api/matters/{$census->id}/census-consented")
        ->assertSuccessful()
        ->assertJsonPath('data', []);
});

test('the consented endpoint rejects non census matters', function () {
    $groupbuy = Matter::factory()->create();
    Sanctum::actingAs(Resident::factory()->admin()->create());

    $this->getJson("/api/matters/{$groupbuy->id}/census-consented")->assertNotFound();
});
