<?php

use App\Models\Matter;
use App\Models\Resident;
use App\Models\Stance;
use App\Settings\CommunitySettings;
use Laravel\Sanctum\Sanctum;

function renovationCensus(array $overrides = []): Matter
{
    return Matter::factory()->create(array_merge([
        'type' => 'census',
        'state' => 'open',
        'category' => '装修',
        'title' => '装修意向摸底',
        'target_count' => 600,
        'payload' => [
            'pitch' => '摸一摸全小区的装修意向',
            'collects_contact' => true,
            'modules' => array_merge([[
                'key' => 'basic',
                'title' => '基础登记',
                'questions' => [
                    ['key' => 'layout', 'text' => '你家是哪个户型？', 'type' => 'single', 'options' => app(CommunitySettings::class)->layouts, 'required' => true],
                    ['key' => 'decoration_mode', 'text' => '打算怎么装？', 'type' => 'single', 'options' => app(CommunitySettings::class)->decoration_modes, 'required' => true],
                    ['key' => 'interests', 'text' => '对哪些团购感兴趣？', 'type' => 'multi', 'options' => app(CommunitySettings::class)->categories, 'required' => true],
                ],
            ], [
                'key' => 'family',
                'title' => '家庭与居住',
                'questions' => [
                    ['key' => 'household_size', 'text' => '常住几口人？', 'type' => 'single', 'options' => ['1~2 人', '3 人', '4 人', '5 人及以上']],
                ],
            ]]),
        ],
    ], $overrides));
}

function basicAnswers(array $overrides = []): array
{
    return array_merge([
        'layout' => app(CommunitySettings::class)->layouts[0],
        'decoration_mode' => app(CommunitySettings::class)->decoration_modes[0],
        'interests' => [app(CommunitySettings::class)->categories[0]],
    ], $overrides);
}

test('the census ships its schema, my answers and aggregates', function () {
    $census = renovationCensus();
    $resident = Resident::factory()->create();
    Stance::factory()->censusAnswers()->for($census, 'matter')->for($resident, 'resident')->create();
    Sanctum::actingAs($resident);

    $this->getJson("/api/matters/{$census->id}/census")
        ->assertSuccessful()
        ->assertJsonPath('title', '装修意向摸底')
        ->assertJsonPath('pitch', '摸一摸全小区的装修意向')
        ->assertJsonPath('modules.0.key', 'basic')
        ->assertJsonPath('collects_contact', true)
        ->assertJsonPath('registered_count', 1)
        ->assertJsonPath('aggregates.0.title', '基础登记');
});

test('a complete member profile is a precondition, not part of the form', function () {
    $census = renovationCensus();
    $resident = Resident::factory()->withoutUnit()->create(['wechat_id' => '']);
    Sanctum::actingAs($resident);

    // 档案不完整：拒绝参与，并且不接收任何联系字段（表单只管答案）
    $this->putJson("/api/matters/{$census->id}/census", [
        'answers' => basicAnswers(),
        'unit_label' => '7栋',
        'wechat_id' => 'laoK-2026',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('profile');

    expect($resident->refresh()->unit_label)->toBe('');

    // 在「个人资料」完善档案后，同样的答卷可以提交
    $this->putJson('/api/me', ['unit_label' => '7栋', 'wechat_id' => 'laoK-2026'])->assertSuccessful();

    $this->putJson("/api/matters/{$census->id}/census", ['answers' => basicAnswers()])
        ->assertCreated()
        ->assertJsonPath('registered_count', 1);

    $stance = Stance::where('mode', Stance::MODE_REGISTER)->first();

    expect($stance->matter_id)->toBe($census->id)
        ->and($stance->payload['answers']['layout'])->toBe(app(CommunitySettings::class)->layouts[0]);
});

test('answers merge module by module and keep a revision trail', function () {
    $census = renovationCensus();
    $resident = Resident::factory()->create();
    Sanctum::actingAs($resident);

    $this->putJson("/api/matters/{$census->id}/census", ['answers' => basicAnswers()])->assertCreated();
    $this->putJson("/api/matters/{$census->id}/census", ['answers' => ['household_size' => '3 人']])
        ->assertSuccessful()
        ->assertJsonPath('answered', 4);

    $stance = Stance::where('mode', Stance::MODE_REGISTER)->first();

    expect(Stance::where('mode', Stance::MODE_REGISTER)->count())->toBe(1)
        ->and($stance->payload['answers'])->toHaveKeys(['layout', 'household_size'])
        ->and($stance->revisions()->count())->toBe(1);
});

test('required questions must be answered before optional modules', function () {
    $census = renovationCensus();
    Sanctum::actingAs(Resident::factory()->create());

    $this->putJson("/api/matters/{$census->id}/census", ['answers' => ['household_size' => '3 人']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('answers');
});

test('the census rejects unknown questions and invalid options', function (array $answers) {
    $census = renovationCensus();
    $resident = Resident::factory()->create();
    Stance::factory()->censusAnswers()->for($census, 'matter')->for($resident, 'resident')->create();
    Sanctum::actingAs($resident);

    $this->putJson("/api/matters/{$census->id}/census", ['answers' => $answers])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('answers');
})->with([
    '未知问题' => [['favorite_car' => '火箭']],
    '未知户型' => [['layout' => '999㎡ · 十房']],
    '多选给了字符串' => [['interests' => '门窗']],
    '多选含未知选项' => [['interests' => ['买飞机']]],
]);

test('merchants register like anyone else and closed censuses stop taking answers', function () {
    $census = renovationCensus();
    Sanctum::actingAs(Resident::factory()->merchant()->create());
    $this->putJson("/api/matters/{$census->id}/census", ['answers' => basicAnswers()])->assertCreated();

    $closed = renovationCensus(['state' => 'closed']);
    Sanctum::actingAs(Resident::factory()->create());
    $this->putJson("/api/matters/{$closed->id}/census", ['answers' => basicAnswers()])->assertUnprocessable();
});

test('census endpoints reject non census matters', function () {
    $groupbuy = Matter::factory()->create();
    Sanctum::actingAs(Resident::factory()->create());

    $this->getJson("/api/matters/{$groupbuy->id}/census")->assertNotFound();
    $this->putJson("/api/matters/{$groupbuy->id}/census", ['answers' => []])->assertNotFound();
});

test('the feed carries register counts and whether I registered', function () {
    $census = renovationCensus();
    $resident = Resident::factory()->create();
    Stance::factory()->censusAnswers()->for($census, 'matter')->for($resident, 'resident')->create();
    Sanctum::actingAs($resident);

    $this->getJson('/api/matters')
        ->assertSuccessful()
        ->assertJsonPath('data.0.type', 'census')
        ->assertJsonPath('data.0.register_count', 1)
        ->assertJsonPath('data.0.registered_by_me', true);
});

test('my censuses ride the profile payload', function () {
    $census = renovationCensus();
    $resident = Resident::factory()->create();
    Stance::factory()->censusAnswers()->for($census, 'matter')->for($resident, 'resident')->create();
    Sanctum::actingAs($resident);

    $this->getJson('/api/me')
        ->assertSuccessful()
        ->assertJsonPath('data.censuses.0.matter_id', $census->id)
        ->assertJsonPath('data.censuses.0.answered', 3);
});

test('guests cannot touch the census', function () {
    $census = renovationCensus();

    $this->getJson("/api/matters/{$census->id}/census")->assertUnauthorized();
    $this->putJson("/api/matters/{$census->id}/census", [])->assertUnauthorized();
});
