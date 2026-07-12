<?php

use App\Models\Matter;
use App\Models\Party;
use App\Models\Resident;
use Laravel\Sanctum\Sanctum;

test('admin publishes a census signed by a party and residents see who is asking', function () {
    Sanctum::actingAs(Resident::factory()->admin()->create());
    $property = Party::factory()->create(['type' => Party::TYPE_PROPERTY, 'name' => '天青府物业服务中心']);

    $response = $this->postJson('/api/matters', [
        'type' => 'census',
        'title' => '电梯改造需求调研',
        'initiator_party_id' => $property->id,
    ])->assertCreated();

    Sanctum::actingAs(Resident::factory()->create());

    $this->getJson('/api/matters/'.$response->json('data.id').'/census')
        ->assertSuccessful()
        ->assertJsonPath('initiator_party.label', '物业')
        ->assertJsonPath('initiator_party.name', '天青府物业服务中心');
});

test('a signed census carries its initiator party in the community feed', function () {
    $committee = Party::factory()->create(['type' => Party::TYPE_COMMITTEE, 'name' => '天青府业委会']);
    Matter::factory()->create([
        'type' => 'census',
        'state' => 'open',
        'initiator_id' => null,
        'initiator_party_id' => $committee->id,
        'payload' => ['pitch' => '', 'modules' => []],
    ]);

    Sanctum::actingAs(Resident::factory()->create());

    $this->getJson('/api/matters')
        ->assertSuccessful()
        ->assertJsonPath('data.0.initiator_party.label', '业委会')
        ->assertJsonPath('data.0.initiator_party.name', '天青府业委会');
});

test('signing requires an existing party', function () {
    Sanctum::actingAs(Resident::factory()->admin()->create());

    $this->postJson('/api/matters', [
        'type' => 'census',
        'title' => '调研',
        'initiator_party_id' => 9999,
    ])->assertJsonValidationErrors(['initiator_party_id']);
});

test('admin removes the signature with an explicit null', function () {
    Sanctum::actingAs(Resident::factory()->admin()->create());
    $property = Party::factory()->create(['type' => Party::TYPE_PROPERTY]);
    $census = Matter::factory()->create([
        'type' => 'census',
        'state' => 'open',
        'initiator_id' => null,
        'initiator_party_id' => $property->id,
        'payload' => ['pitch' => '', 'modules' => []],
    ]);

    $this->putJson('/api/matters/'.$census->id, [
        'title' => $census->title,
        'initiator_party_id' => null,
    ])->assertSuccessful();

    expect($census->refresh()->initiator_party_id)->toBeNull();
});
