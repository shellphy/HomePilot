<?php

use App\Models\Matter;
use App\Models\Resident;
use App\Models\Stance;
use Laravel\Sanctum\Sanctum;

/**
 * 带填空题的收房问题征集：文本题归纳（管理端明细 + 人工归纳 + 公示）的测试基座。
 */
function handoverCensus(): Matter
{
    return Matter::factory()->create([
        'type' => 'census',
        'state' => 'open',
        'category' => '收房',
        'title' => '收房问题摸底',
        'payload' => [
            'modules' => [[
                'key' => 'issues',
                'title' => '房屋问题',
                'questions' => [
                    ['key' => 'issue_types', 'text' => '遇到哪些问题？', 'type' => 'multi', 'options' => ['渗水', '空鼓', '门窗']],
                    ['key' => 'issue_detail', 'text' => '补充说明', 'type' => 'text'],
                ],
            ]],
        ],
    ]);
}

function answerText(Matter $census, string $detail): void
{
    Stance::factory()->for($census, 'matter')->create([
        'mode' => Stance::MODE_REGISTER,
        'payload' => ['answers' => ['issue_types' => ['渗水'], 'issue_detail' => $detail]],
    ]);
}

function reachPublicAggregateThreshold(Matter $census): void
{
    Stance::factory()->count(5)->for($census, 'matter')->create([
        'mode' => Stance::MODE_REGISTER,
        'payload' => ['answers' => ['issue_types' => ['渗水']]],
    ]);
    $census->update(['state' => 'closed']);
}

test('census text endpoints reject ordinary members', function (string $method, string $uri) {
    $census = handoverCensus();
    Sanctum::actingAs(Resident::factory()->create());

    $this->json($method, str_replace('{id}', (string) $census->id, $uri))->assertForbidden();
})->with([
    ['GET', '/api/admin/matters/{id}/census-text'],
    ['PUT', '/api/admin/matters/{id}/census-summary'],
]);

test('admin sees anonymous text answers per question, without identity fields', function () {
    $census = handoverCensus();
    answerText($census, '主卧飘窗渗水，物业没修好');
    answerText($census, '卫生间墙面空鼓');
    Sanctum::actingAs(Resident::factory()->admin()->create());

    $response = $this->getJson("/api/admin/matters/{$census->id}/census-text")
        ->assertSuccessful()
        ->assertJsonPath('title', '收房问题摸底')
        ->assertJsonCount(1, 'questions')
        ->assertJsonPath('questions.0.key', 'issue_detail')
        ->assertJsonPath('questions.0.module_title', '房屋问题')
        ->assertJsonCount(2, 'questions.0.answers')
        ->assertJsonPath('questions.0.summary', null);

    // 明细是纯文本数组：不携带任何住户身份字段
    expect($response->json('questions.0.answers'))
        ->each->toBeString();
});

test('census text detail is not available for non-census matters', function () {
    $groupbuy = Matter::factory()->create(['type' => 'groupbuy']);
    Sanctum::actingAs(Resident::factory()->admin()->create());

    $this->getJson("/api/admin/matters/{$groupbuy->id}/census-text")->assertNotFound();
});

test('a draft summary stays off the public aggregates until published', function () {
    $census = handoverCensus();
    answerText($census, '主卧飘窗渗水');
    reachPublicAggregateThreshold($census);
    Sanctum::actingAs(Resident::factory()->admin()->create());

    $this->putJson("/api/admin/matters/{$census->id}/census-summary", [
        'question_key' => 'issue_detail',
        'themes' => [['title' => '渗水漏水', 'count' => 12, 'note' => '飘窗、卫生间为主']],
        'published' => false,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.published', false);

    // 草稿在管理端可见
    $this->getJson("/api/admin/matters/{$census->id}/census-text")
        ->assertJsonPath('questions.0.summary.themes.0.title', '渗水漏水');

    // 公示面（业主视角）看不到未发布的归纳，文本题整体缺席
    Sanctum::actingAs(Resident::factory()->create());
    $aggregates = $this->getJson("/api/matters/{$census->id}/census")
        ->assertSuccessful()
        ->json('aggregates.0.questions');

    expect(collect($aggregates)->pluck('key'))->not->toContain('issue_detail');
});

test('a published summary shows up in public aggregates as themes, never raw answers', function () {
    $census = handoverCensus();
    answerText($census, '主卧飘窗渗水，13800138000 找我');
    reachPublicAggregateThreshold($census);
    Sanctum::actingAs(Resident::factory()->admin()->create());

    $this->putJson("/api/admin/matters/{$census->id}/census-summary", [
        'question_key' => 'issue_detail',
        'themes' => [
            ['title' => '渗水漏水', 'count' => 12, 'note' => '飘窗、卫生间为主'],
            ['title' => '墙面空鼓', 'count' => 8],
        ],
        'published' => true,
    ])->assertSuccessful();

    Sanctum::actingAs(Resident::factory()->create());
    $response = $this->getJson("/api/matters/{$census->id}/census")->assertSuccessful();

    $textQuestion = collect($response->json('aggregates.0.questions'))->firstWhere('key', 'issue_detail');
    expect($textQuestion['themes'])->toHaveCount(2)
        ->and($textQuestion['themes'][0])->toMatchArray(['title' => '渗水漏水', 'count' => 12, 'note' => '飘窗、卫生间为主'])
        ->and($textQuestion)->not->toHaveKey('counts');

    // 原文永不出现在公示面
    expect(json_encode($response->json(), JSON_UNESCAPED_UNICODE))->not->toContain('13800138000');

    // 选择题聚合不受影响
    $choiceQuestion = collect($response->json('aggregates.0.questions'))->firstWhere('key', 'issue_types');
    expect($choiceQuestion['counts'])->toMatchArray(['渗水' => 6]);
});

test('publishing requires at least one theme and a real text question', function () {
    $census = handoverCensus();
    Sanctum::actingAs(Resident::factory()->admin()->create());

    // 空主题不能发布（可存草稿）
    $this->putJson("/api/admin/matters/{$census->id}/census-summary", [
        'question_key' => 'issue_detail',
        'themes' => [],
        'published' => true,
    ])->assertUnprocessable()->assertJsonValidationErrors('themes');

    // 选择题、不存在的 key 都不接受归纳
    $this->putJson("/api/admin/matters/{$census->id}/census-summary", [
        'question_key' => 'issue_types',
        'themes' => [['title' => '渗水', 'count' => 1]],
        'published' => true,
    ])->assertUnprocessable()->assertJsonValidationErrors('question_key');
});
