<?php

use App\Models\Resident;
use App\Settings\CommunitySettings;
use Laravel\Sanctum\Sanctum;

test('stats report the community overview', function () {
    Resident::factory()->count(3)->create();
    Sanctum::actingAs(Resident::factory()->create());

    $this->getJson('/api/stats')
        ->assertSuccessful()
        ->assertJsonPath('residents', 4)
        ->assertJsonPath('total_households', app(CommunitySettings::class)->total_households);
});

test('guests cannot read stats', function () {
    $this->getJson('/api/stats')->assertUnauthorized();
});
