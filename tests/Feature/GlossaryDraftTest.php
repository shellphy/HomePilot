<?php

use App\Ai\Agents\GlossaryDrafter;
use App\Models\Resident;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Sanctum\Sanctum;

test('a resident hands the drafter a term plus a draft and gets a rewritten explanation', function () {
    GlossaryDrafter::fake(['一台外机带 5 个室内机，三房两厅通常 1 拖 4 就够。']);
    Sanctum::actingAs(Resident::factory()->create());

    $response = $this->postJson('/api/glossary/draft', [
        'term' => '1 拖 5',
        'draft' => '一台外机带五个内机',
        'category' => '中央空调',
    ])->assertSuccessful();

    expect($response->json('data'))
        ->term->toBe('1 拖 5')
        ->explain->toBe('一台外机带 5 个室内机，三房两厅通常 1 拖 4 就够。');

    GlossaryDrafter::assertPrompted(fn (AgentPrompt $prompt) => $prompt->contains('1 拖 5')
        && $prompt->contains('一台外机带五个内机')
        && $prompt->contains('中央空调'));
});

test('rewriting requires both a term and a draft', function () {
    GlossaryDrafter::fake();
    Sanctum::actingAs(Resident::factory()->create());

    $this->postJson('/api/glossary/draft', ['term' => '断桥铝'])
        ->assertJsonValidationErrors(['draft']);

    $this->postJson('/api/glossary/draft', ['draft' => '铝合金中间隔一层隔热条'])
        ->assertJsonValidationErrors(['term']);

    GlossaryDrafter::assertNeverPrompted();
});

test('guests cannot use the drafter', function () {
    GlossaryDrafter::fake();

    $this->postJson('/api/glossary/draft', ['term' => '断桥铝', 'draft' => '隔热条'])
        ->assertUnauthorized();

    GlossaryDrafter::assertNeverPrompted();
});
