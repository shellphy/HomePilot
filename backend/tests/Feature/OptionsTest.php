<?php

use App\Settings\CommunitySettings;

test('options ship community identity, form choices and initiatable matter types', function () {
    $response = $this->getJson('/api/options')
        ->assertSuccessful()
        ->assertJsonPath('community.name', app(CommunitySettings::class)->name)
        ->assertJsonPath('community.pledge', app(CommunitySettings::class)->pledge)
        ->assertJsonPath('layouts', app(CommunitySettings::class)->layouts);

    $types = collect($response->json('matter_types'));

    expect($types->firstWhere('key', 'groupbuy')['user_initiatable'])->toBeTrue()
        ->and($types->firstWhere('key', 'notice')['user_initiatable'])->toBeFalse()
        ->and($types->firstWhere('key', 'census')['user_initiatable'])->toBeFalse();
});
