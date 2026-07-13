<?php

use App\Models\Matter;
use App\Models\Resident;
use App\Models\Stance;
use Laravel\Sanctum\Sanctum;

test('joined lists identify the exact unread matter and opening it clears only that matter', function () {
    $resident = Resident::factory()->create();
    $unread = Matter::factory()->open()->create(['last_activity_at' => now()]);
    $other = Matter::factory()->open()->create(['last_activity_at' => now()]);
    Stance::factory()->for($unread, 'matter')->for($resident, 'resident')->create();
    Stance::factory()->for($other, 'matter')->for($resident, 'resident')->create();
    Sanctum::actingAs($resident);

    $this->getJson('/api/matters/joined')
        ->assertJsonPath('data.0.has_unread_updates', true)
        ->assertJsonPath('data.1.has_unread_updates', true);

    $this->postJson("/api/matters/{$unread->id}/seen")->assertSuccessful();

    $rows = collect($this->getJson('/api/matters/joined')->json('data'));
    expect($rows->firstWhere('id', $unread->id)['has_unread_updates'])->toBeFalse()
        ->and($rows->firstWhere('id', $other->id)['has_unread_updates'])->toBeTrue();
});

test('unrelated residents cannot mark a matter as seen', function () {
    Sanctum::actingAs(Resident::factory()->create());

    $this->postJson('/api/matters/'.Matter::factory()->create()->id.'/seen')->assertForbidden();
});
