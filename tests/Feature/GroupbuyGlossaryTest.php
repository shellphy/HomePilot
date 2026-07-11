<?php

use App\Models\Matter;
use App\Models\Resident;
use Laravel\Sanctum\Sanctum;

test('a groupbuy carries a three-part glossary card (explain, judge, caution)', function () {
    Sanctum::actingAs(Resident::factory()->create(['unit_label' => '3 栋']));

    $response = $this->postJson('/api/matters', [
        'type' => 'groupbuy',
        'category' => '中央空调',
        'title' => '中央空调团购',
        'target_count' => 20,
        'glossary' => [
            [
                'term' => '1 拖 5',
                'explain' => '一台外机带 5 个室内机',
                'judge' => '三房两厅通常 1 拖 4 就够，看会不会同时开',
                'caution' => '多一个内机外机功率也要跟上，问清外机匹数',
            ],
        ],
    ])->assertCreated();

    $matter = Matter::find($response->json('data.id'));

    expect($matter->payloadValue('glossary'))->toBe([
        [
            'term' => '1 拖 5',
            'explain' => '一台外机带 5 个室内机',
            'judge' => '三房两厅通常 1 拖 4 就够，看会不会同时开',
            'caution' => '多一个内机外机功率也要跟上，问清外机匹数',
        ],
    ]);
});

test('legacy two-field glossary entries are still accepted and returned as-is', function () {
    Sanctum::actingAs(Resident::factory()->create(['unit_label' => '3 栋']));

    $response = $this->postJson('/api/matters', [
        'type' => 'groupbuy',
        'category' => '门窗',
        'title' => '门窗团购',
        'target_count' => 15,
        'glossary' => [
            ['term' => '断桥铝', 'explain' => '铝合金中间隔一层隔热条'],
        ],
    ])->assertCreated();

    $this->getJson('/api/matters/'.$response->json('data.id'))
        ->assertSuccessful()
        ->assertJsonPath('data.glossary.0.term', '断桥铝')
        ->assertJsonMissingPath('data.glossary.0.judge');
});

test('glossary judge and caution are length-capped', function () {
    Sanctum::actingAs(Resident::factory()->create(['unit_label' => '3 栋']));

    $this->postJson('/api/matters', [
        'type' => 'groupbuy',
        'category' => '中央空调',
        'title' => '中央空调团购',
        'target_count' => 20,
        'glossary' => [
            ['term' => '压缩机', 'explain' => '空调的心脏', 'judge' => str_repeat('长', 301)],
        ],
    ])->assertJsonValidationErrors(['glossary.0.judge']);
});

test('admin can edit the three-part glossary through the admin form', function () {
    Sanctum::actingAs(Resident::factory()->admin()->create());
    $matter = Matter::factory()->create();

    $this->putJson('/api/admin/matters/'.$matter->id, [
        'title' => $matter->title,
        'category' => $matter->category,
        'target_count' => $matter->target_count,
        'payload' => [
            'glossary' => [
                ['term' => '双转子压缩机', 'explain' => '两个转子轮流做功', 'caution' => '问清压缩机具体型号，答不上来的要警惕'],
            ],
        ],
    ])->assertSuccessful();

    expect($matter->refresh()->payloadValue('glossary')[0]['caution'])
        ->toBe('问清压缩机具体型号，答不上来的要警惕');
});
