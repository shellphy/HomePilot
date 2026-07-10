<?php

use App\Models\Registration;
use App\Models\Resident;
use Laravel\Sanctum\Sanctum;

test('stats aggregate registrations into the progress map data', function () {
    [$layoutA, $layoutB] = array_slice(config('homepilot.layouts'), 0, 2);
    [$modeA, $modeB] = array_slice(config('homepilot.decoration_modes'), 0, 2);
    [$catA, $catB] = array_slice(config('homepilot.categories'), 0, 2);

    Registration::factory()->count(2)->create([
        'layout' => $layoutA,
        'decoration_mode' => $modeA,
        'interests' => [$catA, $catB],
    ]);
    Registration::factory()->create([
        'layout' => $layoutB,
        'decoration_mode' => $modeB,
        'interests' => [$catA],
    ]);

    Sanctum::actingAs(Resident::factory()->create());

    $this->getJson('/api/stats')
        ->assertSuccessful()
        ->assertJsonPath('registered', 3)
        ->assertJsonPath('total_households', config('homepilot.total_households'))
        ->assertJsonPath("layouts.{$layoutA}", 2)
        ->assertJsonPath("layouts.{$layoutB}", 1)
        ->assertJsonPath("decoration_modes.{$modeA}", 2)
        ->assertJsonPath("interests.{$catA}", 3)
        ->assertJsonPath("interests.{$catB}", 2);
});

test('guests cannot read stats', function () {
    $this->getJson('/api/stats')->assertUnauthorized();
});
