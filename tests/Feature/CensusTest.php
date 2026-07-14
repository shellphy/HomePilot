<?php

use App\Models\Matter;
use App\Models\Resident;
use App\Models\Stance;
use Laravel\Sanctum\Sanctum;

function renovationCensus(array $overrides = []): Matter
{
    return Matter::factory()->create(array_merge([
        'type' => 'census',
        'state' => 'open',
        'category' => '装修',
        'title' => '装修意向摸底',
        'target_count' => 600,
        'body' => '摸一摸全小区的装修意向',
        'payload' => [
            'collects_contact' => true,
            'modules' => array_merge([[
                'key' => 'basic',
                'title' => '基础登记',
                'questions' => [
                    ['key' => 'layout', 'text' => '你家是哪个户型？', 'type' => 'single', 'options' => ['107㎡', '130㎡', '154㎡']],
                    ['key' => 'decoration_mode', 'text' => '打算怎么装？', 'type' => 'single', 'options' => ['全包（都交给装修公司）', '半包（主材自己买）']],
                    ['key' => 'interests', 'text' => '对哪些团购感兴趣？', 'type' => 'multi', 'options' => ['装修公司', '门窗', '地暖']],
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
        'layout' => '107㎡',
        'decoration_mode' => '全包（都交给装修公司）',
        'interests' => ['装修公司'],
    ], $overrides);
}

test('me flags an unanswered open census and clears it after answering', function () {
    $census = Matter::factory()->create([
        'type' => 'census',
        'state' => 'open',
        'payload' => [
            'modules' => [[
                'key' => 'm',
                'title' => '基础',
                'questions' => [['key' => 'q1', 'text' => '需要吗？', 'type' => 'single', 'options' => ['要', '不要']]],
            ]],
        ],
    ]);
    Matter::factory()->create(['type' => 'census', 'state' => 'closed']); // 已结束不提示

    Sanctum::actingAs(Resident::factory()->create());
    $this->getJson('/api/me')->assertJsonPath('data.has_unanswered_census', true);

    $this->putJson("/api/matters/{$census->id}/census", ['answers' => ['q1' => '要']])->assertCreated();
    $this->getJson('/api/me')->assertJsonPath('data.has_unanswered_census', false);
});

test('the census ships its schema, my answers and aggregates', function () {
    $census = renovationCensus();
    $resident = Resident::factory()->create();
    Stance::factory()->censusAnswers()->for($census, 'matter')->for($resident, 'resident')->create();
    Stance::factory()->count(4)->censusAnswers()->for($census, 'matter')->create();
    Sanctum::actingAs($resident);

    $this->getJson("/api/matters/{$census->id}/census")
        ->assertSuccessful()
        ->assertJsonPath('title', '装修意向摸底')
        ->assertJsonPath('body', '摸一摸全小区的装修意向')
        ->assertJsonPath('modules.0.key', 'basic')
        ->assertJsonPath('collects_contact', true)
        ->assertJsonPath('registered_count', 5)
        ->assertJsonPath('aggregates.0.title', '基础登记');
});

test('a complete member profile is a precondition, not part of the form', function () {
    $census = renovationCensus();
    $resident = Resident::factory()->withoutUnit()->create(['phone' => '']);
    Sanctum::actingAs($resident);

    // 档案不完整：拒绝参与，并且不接收任何联系字段（表单只管答案）
    $this->putJson("/api/matters/{$census->id}/census", [
        'answers' => basicAnswers(),
        'unit_label' => '7栋',
        'phone' => '13800138000',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('profile');

    expect($resident->refresh()->unit_label)->toBe('');

    // 在「个人资料」填好楼栋、手机号并保存后，同样的答卷可以提交
    $this->putJson('/api/me', ['unit_label' => '7栋', 'phone' => '13800138000'])->assertSuccessful();

    $this->putJson("/api/matters/{$census->id}/census", ['answers' => basicAnswers()])
        ->assertCreated()
        ->assertJsonPath('registered_count', 1);

    $stance = Stance::where('mode', Stance::MODE_REGISTER)->first();

    expect($stance->matter_id)->toBe($census->id)
        ->and($stance->payload['answers']['layout'])->toBe('107㎡');
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

test('any subset of answers can be submitted (no question is mandatory)', function () {
    $census = renovationCensus();
    Sanctum::actingAs(Resident::factory()->create());

    // 没有「必答」概念：只答一道后置模块的题也能提交成功
    $this->putJson("/api/matters/{$census->id}/census", ['answers' => ['household_size' => '3 人']])
        ->assertSuccessful();
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

test('text questions accept free-form answers but stay out of the public aggregates', function () {
    $census = Matter::factory()->create([
        'type' => 'census',
        'state' => 'open',
        'title' => '装修探店摸底',
        'payload' => [
            'modules' => [[
                'key' => 'visits',
                'title' => '探店情况',
                'questions' => [
                    ['key' => 'visited', 'text' => '去看过哪些装修公司？', 'type' => 'multi', 'options' => ['A 公司', 'B 公司']],
                    ['key' => 'pitfall', 'text' => '踩过什么坑或有什么心得？', 'type' => 'text', 'note' => '只做管理端参考，不公开展示'],
                ],
            ]],
        ],
    ]);
    Sanctum::actingAs(Resident::factory()->create());

    $this->putJson("/api/matters/{$census->id}/census", [
        'answers' => ['visited' => ['A 公司'], 'pitfall' => '定金说随时退，合同里却写不退，签约前要问清'],
    ])->assertCreated();
    Stance::factory()->count(4)->for($census, 'matter')->create([
        'mode' => Stance::MODE_REGISTER,
        'payload' => ['answers' => ['visited' => ['A 公司']]],
    ]);

    // 公示聚合只有选择题；填空题的原文不出现在公开面
    $aggregates = $this->getJson("/api/matters/{$census->id}/census")
        ->assertSuccessful()
        ->json('aggregates.0.questions');
    expect(collect($aggregates)->pluck('key')->all())->toBe(['visited']);
});

test('blank or oversized text answers are rejected', function () {
    $census = Matter::factory()->create([
        'type' => 'census',
        'state' => 'open',
        'payload' => [
            'modules' => [[
                'key' => 'visits',
                'title' => '探店情况',
                'questions' => [
                    ['key' => 'pitfall', 'text' => '踩过什么坑？', 'type' => 'text'],
                ],
            ]],
        ],
    ]);
    Sanctum::actingAs(Resident::factory()->create());

    $this->putJson("/api/matters/{$census->id}/census", ['answers' => ['pitfall' => '   ']])
        ->assertUnprocessable();
    $this->putJson("/api/matters/{$census->id}/census", ['answers' => ['pitfall' => str_repeat('长', 501)]])
        ->assertUnprocessable();
});
