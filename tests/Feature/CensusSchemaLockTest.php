<?php

use App\Models\Matter;
use App\Models\Resident;
use App\Models\Stance;
use Laravel\Sanctum\Sanctum;

/**
 * @param  'draft'|'approved'  $status
 */
function lockCensus(Resident $initiator, string $status = 'approved'): Matter
{
    $factory = Matter::factory()->for($initiator, 'initiator');
    if ($status === 'draft') {
        $factory = $factory->draft();
    }

    return $factory->create([
        'type' => 'census',
        'state' => 'open',
        'title' => '装修偏好摸底',
        'body' => '摸个底',
        'payload' => [
            'modules' => [[
                'key' => 'm1',
                'title' => '偏好',
                'questions' => [[
                    'key' => 'q1',
                    'text' => '你想怎么装？',
                    'type' => 'single',
                    'options' => ['全包', '半包'],
                ]],
            ]],
        ],
    ]);
}

/** @return array<string, mixed> 原样保留 q1，再追加一道新题 */
function additivePayload(): array
{
    return [
        'title' => '装修偏好摸底',
        'modules' => [[
            'key' => 'm1',
            'title' => '偏好',
            'questions' => [
                ['key' => 'q1', 'text' => '你想怎么装？', 'type' => 'single', 'options' => ['全包', '半包']],
                ['text' => '预算大概多少？', 'type' => 'single', 'options' => ['10 万内', '10-20 万']],
            ],
        ]],
    ];
}

test('发起者可以给已公示的征集新增题目', function () {
    $initiator = Resident::factory()->create();
    $matter = lockCensus($initiator);
    Sanctum::actingAs($initiator);

    $this->putJson("/api/matters/{$matter->id}", additivePayload())
        ->assertSuccessful();

    $questions = $matter->refresh()->payloadList('modules')[0]['questions'];
    expect($questions)->toHaveCount(2)
        ->and($questions[0]['text'])->toBe('你想怎么装？');
});

test('发起者不能改动已公示征集的已有题目', function () {
    $initiator = Resident::factory()->create();
    $matter = lockCensus($initiator);
    Sanctum::actingAs($initiator);

    $payload = [
        'title' => '装修偏好摸底',
        'modules' => [[
            'key' => 'm1',
            'title' => '偏好',
            'questions' => [[
                'key' => 'q1',
                'text' => '你想怎么装修？改了题面', // 改动已有题目
                'type' => 'single',
                'options' => ['全包', '半包'],
            ]],
        ]],
    ];

    $this->putJson("/api/matters/{$matter->id}", $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('modules');

    expect($matter->refresh()->payloadList('modules')[0]['questions'][0]['text'])->toBe('你想怎么装？');
});

test('发起者不能删除已公示征集的已有题目', function () {
    $initiator = Resident::factory()->create();
    $matter = lockCensus($initiator);
    Sanctum::actingAs($initiator);

    $payload = [
        'title' => '装修偏好摸底',
        'modules' => [[
            'key' => 'm1',
            'title' => '偏好',
            // 只留一道新题，丢掉 q1
            'questions' => [['text' => '预算多少？', 'type' => 'single', 'options' => ['少', '多']]],
        ]],
    ];

    $this->putJson("/api/matters/{$matter->id}", $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('modules');
});

test('尚未公示的草稿征集，发起者可以自由改题', function () {
    $initiator = Resident::factory()->create();
    $matter = lockCensus($initiator, 'draft');
    Sanctum::actingAs($initiator);

    $payload = [
        'title' => '装修偏好摸底',
        'modules' => [[
            'key' => 'm1',
            'title' => '偏好',
            'questions' => [[
                'key' => 'q1',
                'text' => '随便改草稿的题面', // 草稿期改题不拦
                'type' => 'single',
                'options' => ['全包', '半包'],
            ]],
        ]],
    ];

    $this->putJson("/api/matters/{$matter->id}", $payload)->assertSuccessful();
    expect($matter->refresh()->payloadList('modules')[0]['questions'][0]['text'])->toBe('随便改草稿的题面');
});

test('已有邻居作答的征集即便未公示也锁定已有题目', function () {
    $initiator = Resident::factory()->create();
    $matter = lockCensus($initiator, 'draft');
    Stance::factory()->censusAnswers()->create([
        'matter_id' => $matter->id,
        'resident_id' => Resident::factory()->create()->id,
    ]);
    Sanctum::actingAs($initiator);

    $payload = [
        'title' => '装修偏好摸底',
        'modules' => [[
            'key' => 'm1',
            'title' => '偏好',
            'questions' => [['key' => 'q1', 'text' => '改题面', 'type' => 'single', 'options' => ['全包', '半包']]],
        ]],
    ];

    $this->putJson("/api/matters/{$matter->id}", $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('modules');
});

test('管理员纠错通道不受加题限制，可改动已公示征集的已有题目', function () {
    $initiator = Resident::factory()->create();
    $matter = lockCensus($initiator);
    Sanctum::actingAs(Resident::factory()->admin()->create());

    $payload = [
        'title' => '装修偏好摸底',
        'modules' => [[
            'key' => 'm1',
            'title' => '偏好',
            'questions' => [[
                'key' => 'q1',
                'text' => '管理员纠错改的题面',
                'type' => 'single',
                'options' => ['全包', '半包'],
            ]],
        ]],
    ];

    $this->putJson("/api/matters/{$matter->id}", $payload)->assertSuccessful();
    expect($matter->refresh()->payloadList('modules')[0]['questions'][0]['text'])->toBe('管理员纠错改的题面');
});
