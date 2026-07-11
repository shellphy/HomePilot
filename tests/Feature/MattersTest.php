<?php

use App\Models\Matter;
use App\Models\Resident;
use App\Models\Stance;
use Laravel\Sanctum\Sanctum;

test('the matter feed puts active groupbuys first and finished ones last', function () {
    $done = Matter::factory()->done()->create();
    $seeking = Matter::factory()->create();
    $open = Matter::factory()->open()->create();

    Sanctum::actingAs(Resident::factory()->create());

    $this->getJson('/api/matters')
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $open->id)
        ->assertJsonPath('data.1.id', $seeking->id)
        ->assertJsonPath('data.2.id', $done->id)
        ->assertJsonPath('data.0.state_label', '接龙中');
});

test('published notices ride the same feed and pin to the top', function () {
    Matter::factory()->open()->create();
    $notice = Matter::factory()->notice()->create(['title' => '公益运营说明']);
    $archived = Matter::factory()->notice()->create(['state' => 'archived']);

    Sanctum::actingAs(Resident::factory()->create());

    $response = $this->getJson('/api/matters')->assertSuccessful();

    expect($response->json('data.0.id'))->toBe($notice->id)
        ->and($response->json('data.0.type'))->toBe('notice')
        ->and(collect($response->json('data'))->pluck('id'))->not->toContain($archived->id);
});

test('the matter feed carries join counts', function () {
    $matter = Matter::factory()->open()->create();
    Stance::factory()->count(3)->for($matter, 'matter')->create();

    Sanctum::actingAs(Resident::factory()->create());

    $this->getJson('/api/matters')
        ->assertSuccessful()
        ->assertJsonPath('data.0.join_count', 3);
});

test('the matter detail shows the roster and whether I joined', function () {
    $matter = Matter::factory()->open()->create();
    $neighbor = Resident::factory()->inUnit('5栋')->create(['nickname' => '老王']);
    Stance::factory()->for($matter, 'matter')->for($neighbor, 'resident')->create();

    Sanctum::actingAs(Resident::factory()->create());

    $this->getJson("/api/matters/{$matter->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.roster.0', '5栋 老王')
        ->assertJsonPath('joined', false);
});

test('rights rosters stay private: count only for others, full list for the initiator', function () {
    $initiator = Resident::factory()->create();
    $matter = Matter::factory()->rights()->for($initiator, 'initiator')->create();
    $neighbor = Resident::factory()->inUnit('5栋')->create(['nickname' => '老王']);
    Stance::factory()->for($matter, 'matter')->for($neighbor, 'resident')->create();

    // 旁观者：只见计数，不见名单
    Sanctum::actingAs(Resident::factory()->create());
    $this->getJson("/api/matters/{$matter->id}")
        ->assertJsonPath('data.roster_hidden', true)
        ->assertJsonPath('data.roster', [])
        ->assertJsonPath('data.join_count', 1);

    // 牵头人：明细可见
    Sanctum::actingAs($initiator);
    $this->getJson("/api/matters/{$matter->id}")
        ->assertJsonPath('data.roster.0', '5栋 老王');
});

test('a resident can join a matter and the count grows', function () {
    $matter = Matter::factory()->open()->create();
    Sanctum::actingAs(Resident::factory()->create());

    $this->postJson("/api/matters/{$matter->id}/join")
        ->assertCreated()
        ->assertJsonPath('joined', true)
        ->assertJsonPath('join_count', 1);
});

test('joining twice stays idempotent', function () {
    $matter = Matter::factory()->open()->create();
    Sanctum::actingAs(Resident::factory()->create());

    $this->postJson("/api/matters/{$matter->id}/join")->assertCreated();
    $this->postJson("/api/matters/{$matter->id}/join")
        ->assertCreated()
        ->assertJsonPath('join_count', 1);

    expect(Stance::where('mode', Stance::MODE_JOIN)->count())->toBe(1);
});

test('a resident can cancel their join', function () {
    $matter = Matter::factory()->open()->create();
    $resident = Resident::factory()->create();
    Stance::factory()->for($matter, 'matter')->for($resident, 'resident')->create();
    Sanctum::actingAs($resident);

    $this->deleteJson("/api/matters/{$matter->id}/join")
        ->assertSuccessful()
        ->assertJsonPath('joined', false)
        ->assertJsonPath('join_count', 0);

    expect(Stance::where('mode', Stance::MODE_JOIN)->count())->toBe(0);
});

test('cancelling never touches other residents joins', function () {
    $matter = Matter::factory()->open()->create();
    $neighbor = Resident::factory()->create();
    Stance::factory()->for($matter, 'matter')->for($neighbor, 'resident')->create();

    Sanctum::actingAs(Resident::factory()->create());

    $this->deleteJson("/api/matters/{$matter->id}/join")
        ->assertSuccessful()
        ->assertJsonPath('join_count', 1);

    expect(Stance::where('mode', Stance::MODE_JOIN)->count())->toBe(1);
});

test('joining a finished groupbuy is rejected', function () {
    $matter = Matter::factory()->done()->create();
    Sanctum::actingAs(Resident::factory()->create());

    $this->postJson("/api/matters/{$matter->id}/join")->assertUnprocessable();
});

test('notices cannot be joined at all', function () {
    $notice = Matter::factory()->notice()->create();
    Sanctum::actingAs(Resident::factory()->create());

    $this->postJson("/api/matters/{$notice->id}/join")->assertUnprocessable();
});

test('activities aids and rights actions are joinable while active', function (string $factoryState) {
    $matter = Matter::factory()->{$factoryState}()->create();
    Sanctum::actingAs(Resident::factory()->create());

    $this->postJson("/api/matters/{$matter->id}/join")
        ->assertCreated()
        ->assertJsonPath('join_count', 1);
})->with(['activity', 'aid', 'rights']);

test('a resolved rights action stops taking signatures', function () {
    $matter = Matter::factory()->rights()->create(['state' => 'resolved']);
    Sanctum::actingAs(Resident::factory()->create());

    $this->postJson("/api/matters/{$matter->id}/join")->assertUnprocessable();
});

test('the joined list carries only matters I have a join record on', function () {
    $resident = Resident::factory()->create();
    $joined = Matter::factory()->open()->create();
    Stance::factory()->for($joined, 'matter')->for($resident, 'resident')->create();
    Matter::factory()->open()->create(); // 别人的事，不该出现

    Sanctum::actingAs($resident);

    $this->getJson('/api/matters/joined')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $joined->id);
});

test('guests cannot browse matters', function () {
    $this->getJson('/api/matters')->assertUnauthorized();
});
