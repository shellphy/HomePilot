<?php

use App\Models\Matter;
use App\Models\Resident;
use Laravel\Sanctum\Sanctum;

test('a matter exposes its raw payload to the initiator so they can edit it', function () {
    $owner = Resident::factory()->create(['unit_label' => '1栋']);
    Sanctum::actingAs($owner);

    $census = Matter::factory()->create([
        'type' => 'census',
        'state' => 'open',
        'initiator_id' => $owner->id,
        'payload' => ['purpose' => '开团前摸底', 'modules' => [['key' => 'm1', 'title' => '预算', 'questions' => []]]],
    ]);

    $this->getJson('/api/matters/'.$census->id)
        ->assertSuccessful()
        ->assertJsonPath('data.payload.purpose', '开团前摸底')
        ->assertJsonPath('data.payload.modules.0.title', '预算');
});

test('a non-initiator member does not receive the raw payload', function () {
    $census = Matter::factory()->create([
        'type' => 'census',
        'state' => 'open',
        'payload' => ['purpose' => '开团前摸底', 'modules' => []],
    ]);

    Sanctum::actingAs(Resident::factory()->create());

    $this->getJson('/api/matters/'.$census->id)
        ->assertSuccessful()
        ->assertJsonMissingPath('data.payload');
});
