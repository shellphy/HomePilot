<?php

use App\Models\Matter;
use App\Models\Resident;
use Laravel\Sanctum\Sanctum;

test('a groupbuy carries a glossary of term + free-text explanation', function () {
    Sanctum::actingAs(Resident::factory()->create(['unit_label' => '3 栋']));

    $response = $this->postJson('/api/matters', [
        'type' => 'groupbuy',
        'relationship' => 'none',
        'category' => '中央空调',
        'title' => '中央空调团购',
        'target_count' => 20,
        'glossary' => [
            [
                'term' => '1 拖 5',
                'explain' => '一台外机带 5 个室内机；三房两厅通常 1 拖 4 就够，多一个内机外机功率要跟上，问清外机匹数。',
            ],
        ],
    ])->assertCreated();

    $matter = Matter::find($response->json('data.id'));

    expect($matter->payloadValue('glossary'))->toBe([
        [
            'term' => '1 拖 5',
            'explain' => '一台外机带 5 个室内机；三房两厅通常 1 拖 4 就够，多一个内机外机功率要跟上，问清外机匹数。',
        ],
    ]);
});

test('glossary entries require a term and an explanation', function () {
    Sanctum::actingAs(Resident::factory()->create(['unit_label' => '3 栋']));

    $this->postJson('/api/matters', [
        'type' => 'groupbuy',
        'relationship' => 'none',
        'category' => '门窗',
        'title' => '门窗团购',
        'target_count' => 15,
        'glossary' => [
            ['term' => '断桥铝'],
        ],
    ])->assertJsonValidationErrors(['glossary.0.explain']);
});

test('the glossary explanation is length-capped', function () {
    Sanctum::actingAs(Resident::factory()->create(['unit_label' => '3 栋']));

    $this->postJson('/api/matters', [
        'type' => 'groupbuy',
        'relationship' => 'none',
        'category' => '中央空调',
        'title' => '中央空调团购',
        'target_count' => 20,
        'glossary' => [
            ['term' => '压缩机', 'explain' => str_repeat('长', 501)],
        ],
    ])->assertJsonValidationErrors(['glossary.0.explain']);
});

test('admin can edit the glossary through the admin form', function () {
    Sanctum::actingAs(Resident::factory()->admin()->create());
    $matter = Matter::factory()->create();

    $this->putJson('/api/matters/'.$matter->id, [
        'title' => $matter->title,
        'category' => $matter->category,
        'target_count' => $matter->target_count,
        'glossary' => [
            ['term' => '双转子压缩机', 'explain' => '两个转子轮流做功，问清压缩机具体型号，答不上来的要警惕'],
        ],
    ])->assertSuccessful();

    expect($matter->refresh()->payloadValue('glossary')[0]['explain'])
        ->toBe('两个转子轮流做功，问清压缩机具体型号，答不上来的要警惕');
});
