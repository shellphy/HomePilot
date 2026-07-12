<?php

use App\Models\Matter;
use App\Models\Resident;
use Laravel\Sanctum\Sanctum;

test('a census stores a free-text purpose in its payload', function () {
    Sanctum::actingAs(Resident::factory()->admin()->create());

    $response = $this->postJson('/api/matters', [
        'type' => 'census',
        'title' => '需求摸底',
        'purpose' => '开团前先摸清大家想装什么',
    ])->assertCreated();

    expect(Matter::find($response->json('data.id'))->payloadValue('purpose'))
        ->toBe('开团前先摸清大家想装什么');
});

test('the census endpoint exposes the purpose', function () {
    $census = Matter::factory()->create([
        'type' => 'census',
        'state' => 'open',
        'payload' => ['purpose' => '想了解大家的预算档位', 'modules' => []],
    ]);

    Sanctum::actingAs(Resident::factory()->create());

    $this->getJson('/api/matters/'.$census->id.'/census')
        ->assertSuccessful()
        ->assertJsonPath('purpose', '想了解大家的预算档位');
});
