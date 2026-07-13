<?php

use App\Models\Matter;
use App\Models\Resident;
use Laravel\Sanctum\Sanctum;

test('activities expose structured schedule fields and stop accepting joins after the deadline', function () {
    $matter = Matter::factory()->activity()->create([
        'starts_at' => now()->addDay(),
        'registration_deadline_at' => now()->subMinute(),
        'location' => '北门广场',
    ]);
    Sanctum::actingAs(Resident::factory()->create(['unit_label' => '3栋']));

    $this->getJson("/api/matters/{$matter->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.location', '北门广场')
        ->assertJsonPath('data.registration_closed', true)
        ->assertJsonPath('data.join_open', false);

    $this->postJson("/api/matters/{$matter->id}/join")->assertUnprocessable();
});

test('the scheduler closes activities and aid after their start time', function () {
    $activity = Matter::factory()->activity()->create(['starts_at' => now()->subMinute()]);
    $aid = Matter::factory()->aid()->create(['starts_at' => now()->subMinute()]);
    $future = Matter::factory()->activity()->create(['starts_at' => now()->addHour()]);

    $this->artisan('matters:close-expired')->assertSuccessful();

    expect($activity->refresh()->state)->toBe('done')
        ->and($aid->refresh()->state)->toBe('closed')
        ->and($future->refresh()->state)->toBe('open');
});
