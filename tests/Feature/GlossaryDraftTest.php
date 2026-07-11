<?php

use App\Ai\Agents\GlossaryDrafter;
use App\Models\Resident;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Sanctum\Sanctum;

test('a resident gets a three-part draft for a term with the category as context', function () {
    GlossaryDrafter::fake();
    Sanctum::actingAs(Resident::factory()->create());

    $response = $this->postJson('/api/glossary/draft', [
        'term' => '双转子压缩机',
        'category' => '中央空调',
    ])->assertSuccessful();

    expect($response->json('data'))
        ->term->toBe('双转子压缩机')
        ->explain->toBeString()
        ->judge->toBeString()
        ->caution->toBeString();

    GlossaryDrafter::assertPrompted(fn (AgentPrompt $prompt) => $prompt->contains('双转子压缩机') && $prompt->contains('中央空调'));
});

test('drafting requires a term', function () {
    GlossaryDrafter::fake();
    Sanctum::actingAs(Resident::factory()->create());

    $this->postJson('/api/glossary/draft', ['category' => '中央空调'])
        ->assertJsonValidationErrors(['term']);

    GlossaryDrafter::assertNeverPrompted();
});

test('guests cannot use the drafter', function () {
    GlossaryDrafter::fake();

    $this->postJson('/api/glossary/draft', ['term' => '断桥铝'])->assertUnauthorized();

    GlossaryDrafter::assertNeverPrompted();
});
