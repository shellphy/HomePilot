<?php

use App\Settings\CommunitySettings;

test('options ship community identity, form choices and initiatable matter types', function () {
    $response = $this->getJson('/api/options')
        ->assertSuccessful()
        ->assertJsonPath('community.name', app(CommunitySettings::class)->name)
        ->assertJsonPath('buildings', app(CommunitySettings::class)->buildings)
        ->assertJsonPath('layouts', app(CommunitySettings::class)->layouts);

    $types = collect($response->json('matter_types'));

    expect($types->firstWhere('key', 'groupbuy')['user_initiatable'])->toBeTrue()
        ->and($types->firstWhere('key', 'notice')['user_initiatable'])->toBeFalse()
        ->and($types->firstWhere('key', 'census')['user_initiatable'])->toBeTrue();
});

test('ai feature switches ship per capability', function () {
    config([
        'features.ai.chat' => true,
        'features.ai.census_report' => false,
        'features.ai.glossary_draft' => true,
    ]);

    $this->getJson('/api/options')
        ->assertSuccessful()
        ->assertJsonPath('ai.chat', true)
        ->assertJsonPath('ai.census_report', false)
        ->assertJsonPath('ai.glossary_draft', true);
});
