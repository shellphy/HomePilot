<?php

use App\Models\Matter;
use App\Models\Resident;
use Laravel\Sanctum\Sanctum;

test('admin saves per-option explanations alongside the options', function () {
    Sanctum::actingAs(Resident::factory()->admin()->create());

    $response = $this->postJson('/api/admin/matters', [
        'type' => 'census',
        'title' => '定制柜摸底',
        'payload' => [
            'modules' => [[
                'title' => '柜体',
                'questions' => [[
                    'text' => '柜体倾向哪种板材？',
                    'type' => 'single',
                    'options' => ['颗粒板', '多层实木', '还没概念'],
                    'option_notes' => ['便宜，环保看等级，主流选择', '贵约三成，更防潮', ''],
                ]],
            ]],
        ],
    ])->assertCreated();

    $question = Matter::find($response->json('data.id'))->payloadValue('modules')[0]['questions'][0];

    // 空解释经全局中间件转为 null，前端展示时按空处理
    expect($question['option_notes'])->toBe(['便宜，环保看等级，主流选择', '贵约三成，更防潮', null]);
});

test('option explanations ship to residents with the census schema', function () {
    $census = Matter::factory()->create([
        'type' => 'census',
        'state' => 'open',
        'payload' => [
            'modules' => [[
                'key' => 'm1',
                'title' => '柜体',
                'questions' => [[
                    'key' => 'q1',
                    'text' => '柜体倾向哪种板材？',
                    'type' => 'single',
                    'options' => ['颗粒板', '多层实木'],
                    'option_notes' => ['便宜，环保看等级', '贵约三成，更防潮'],
                ]],
            ]],
        ],
    ]);

    Sanctum::actingAs(Resident::factory()->create());

    $this->getJson('/api/matters/'.$census->id.'/census')
        ->assertSuccessful()
        ->assertJsonPath('modules.0.questions.0.option_notes.0', '便宜，环保看等级');
});

test('overlong option explanations are rejected', function () {
    Sanctum::actingAs(Resident::factory()->admin()->create());

    $this->postJson('/api/admin/matters', [
        'type' => 'census',
        'title' => '定制柜摸底',
        'payload' => [
            'modules' => [[
                'title' => '柜体',
                'questions' => [[
                    'text' => '板材？',
                    'type' => 'single',
                    'options' => ['颗粒板', '多层实木'],
                    'option_notes' => [str_repeat('长', 101), ''],
                ]],
            ]],
        ],
    ])->assertJsonValidationErrors(['payload.modules.0.questions.0.option_notes.0']);
});
