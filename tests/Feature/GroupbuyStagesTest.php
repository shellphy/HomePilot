<?php

use App\Models\Matter;
use App\Models\Resident;
use App\Models\Stance;
use Laravel\Sanctum\Sanctum;

// ---- 两段表态：条款没谈出来前报名只是意向，接龙中报名/确认才计入成团 ----

test('joining before the terms are settled records an intent, joining an open roster confirms', function () {
    Sanctum::actingAs(Resident::factory()->create());

    $seeking = Matter::factory()->create();
    $this->postJson("/api/matters/{$seeking->id}/join")->assertCreated();
    expect($seeking->joins()->sole()->payload['stage'])->toBe(Stance::JOIN_STAGE_INTENT);

    $open = Matter::factory()->open()->create();
    $this->postJson("/api/matters/{$open->id}/join")->assertCreated();
    expect($open->joins()->sole()->payload['stage'])->toBe(Stance::JOIN_STAGE_CONFIRMED);
});

test('the detail splits interest from commitment and exposes my stage', function () {
    $matter = Matter::factory()->open()->create(['target_count' => 10]);
    $intender = Resident::factory()->create();
    Stance::factory()->for($matter, 'matter')->for($intender, 'resident')->intent()->create();
    Stance::factory()->for($matter, 'matter')->create(['payload' => ['share_contact' => true, 'stage' => Stance::JOIN_STAGE_CONFIRMED]]);

    Sanctum::actingAs($intender);
    $this->getJson("/api/matters/{$matter->id}")
        ->assertJsonPath('data.join_count', 2)
        ->assertJsonPath('data.confirmed_count', 1)
        ->assertJsonPath('my_join_stage', 'intent');
});

test('rejoining an open roster upgrades an intent to a confirmation with a revision trail', function () {
    $matter = Matter::factory()->open()->create();
    $resident = Resident::factory()->create();
    Stance::factory()->for($matter, 'matter')->for($resident, 'resident')->intent()->create();

    Sanctum::actingAs($resident);
    $this->postJson("/api/matters/{$matter->id}/join", ['share_contact' => true])->assertCreated();

    $join = $matter->joins()->sole();
    expect($join->payload['stage'])->toBe(Stance::JOIN_STAGE_CONFIRMED)
        ->and($join->revisions()->count())->toBe(1)
        ->and($join->revisions()->first()->payload['stage'])->toBe(Stance::JOIN_STAGE_INTENT);

    // 已确认的重复报名不再产生修订
    $this->postJson("/api/matters/{$matter->id}/join", ['share_contact' => true])->assertCreated();
    expect($join->refresh()->revisions()->count())->toBe(1);
});

test('an intent left hanging after the deal closes stays an intent', function () {
    $matter = Matter::factory()->done()->create();
    $resident = Resident::factory()->create();
    Stance::factory()->for($matter, 'matter')->for($resident, 'resident')->intent(shareContact: false)->create();

    // 成团后补开共享仍可以，但承诺档位不会被悄悄升级
    Sanctum::actingAs($resident);
    $this->postJson("/api/matters/{$matter->id}/join", ['share_contact' => true])->assertCreated();

    $join = $matter->joins()->sole();
    expect($join->payload['share_contact'])->toBeTrue()
        ->and($join->payload['stage'])->toBe(Stance::JOIN_STAGE_INTENT);
});

test('legacy joins without a stage count as confirmed', function () {
    $matter = Matter::factory()->done()->create();
    Stance::factory()->for($matter, 'matter')->create(['payload' => ['share_contact' => true]]);

    Sanctum::actingAs(Resident::factory()->create());
    $this->getJson("/api/matters/{$matter->id}")->assertJsonPath('data.confirmed_count', 1);
});

test('only confirmed participants can review a done groupbuy', function () {
    $matter = Matter::factory()->done()->create();
    $intender = Resident::factory()->create();
    $confirmed = Resident::factory()->create();
    Stance::factory()->for($matter, 'matter')->for($intender, 'resident')->intent()->create();
    Stance::factory()->for($matter, 'matter')->for($confirmed, 'resident')->create(['payload' => ['share_contact' => true, 'stage' => Stance::JOIN_STAGE_CONFIRMED]]);

    Sanctum::actingAs($intender);
    $this->putJson("/api/matters/{$matter->id}/review", ['rating' => 5])->assertForbidden();

    Sanctum::actingAs($confirmed);
    $this->putJson("/api/matters/{$matter->id}/review", ['rating' => 5])->assertCreated();
});

test('a standard done deal exchanges contacts only with confirmed participants', function () {
    $initiator = Resident::factory()->inUnit('3栋')->create(['nickname' => '老K', 'phone' => '13900000000']);
    $matter = Matter::factory()->done()->for($initiator, 'initiator')->create();

    $intender = Resident::factory()->create(['phone' => '13833334444']);
    $confirmed = Resident::factory()->inUnit('5栋')->create(['nickname' => '老王', 'phone' => '13811112222']);
    Stance::factory()->for($matter, 'matter')->for($intender, 'resident')->intent()->create();
    Stance::factory()->for($matter, 'matter')->for($confirmed, 'resident')->create(['payload' => ['share_contact' => true, 'stage' => Stance::JOIN_STAGE_CONFIRMED]]);

    // 团长只看到确认参团的人：登记过意向不等于进了成交名单
    Sanctum::actingAs($initiator);
    expect($this->getJson("/api/matters/{$matter->id}")->json('contacts'))
        ->toBe([['name' => '5栋 老王', 'phone' => '13811112222']]);

    // 意向档的参与者也看不到团长电话（对等）
    Sanctum::actingAs($intender);
    $this->getJson("/api/matters/{$matter->id}")->assertJsonPath('initiator_contact', null);
});

// ---- 方案型团购（needs_survey）：量房必须发生在成团前，联系互通提前到谈判中 ----

test('a survey groupbuy opens contacts during negotiation so the merchant can visit homes', function () {
    $initiator = Resident::factory()->inUnit('3栋')->create(['nickname' => '老K', 'phone' => '13900000000']);
    $matter = Matter::factory()->survey()->negotiating()->for($initiator, 'initiator')->create();

    $intender = Resident::factory()->inUnit('5栋')->create(['nickname' => '老王', 'phone' => '13811112222']);
    Stance::factory()->for($matter, 'matter')->for($intender, 'resident')->intent()->create();

    // 方案型团里报名本身就是约量房：意向档也进互通名单
    Sanctum::actingAs($initiator);
    expect($this->getJson("/api/matters/{$matter->id}")->json('contacts'))
        ->toBe([['name' => '5栋 老王', 'phone' => '13811112222']]);

    Sanctum::actingAs($intender);
    $this->getJson("/api/matters/{$matter->id}")
        ->assertJsonPath('data.contacts_open', true)
        ->assertJsonPath('initiator_contact.phone', '13900000000');
});

test('contacts stay closed while a survey groupbuy is still seeking interest, and for standard negotiation', function () {
    $initiator = Resident::factory()->create(['phone' => '13900000000']);
    $participant = Resident::factory()->create(['phone' => '13811112222']);

    $surveySeeking = Matter::factory()->survey()->for($initiator, 'initiator')->create();
    Stance::factory()->for($surveySeeking, 'matter')->for($participant, 'resident')->intent()->create();

    $standardNegotiating = Matter::factory()->negotiating()->for($initiator, 'initiator')->create();
    Stance::factory()->for($standardNegotiating, 'matter')->for($participant, 'resident')->intent()->create();

    Sanctum::actingAs($initiator);
    expect($this->getJson("/api/matters/{$surveySeeking->id}")->json('contacts'))->toBe([])
        ->and($this->getJson("/api/matters/{$standardNegotiating->id}")->json('contacts'))->toBe([]);
});

test('the survey flag is chosen at creation and the initiator cannot flip it afterwards', function () {
    Sanctum::actingAs($initiator = Resident::factory()->inUnit('3栋')->create());

    $id = $this->postJson('/api/matters', [
        'type' => 'groupbuy',
        'title' => '中央空调团购',
        'category' => '中央空调',
        'target_count' => 15,
        'needs_survey' => true,
    ])->assertCreated()->json('data.id');

    $this->putJson("/api/matters/{$id}", [
        'title' => '中央空调团购',
        'category' => '中央空调',
        'target_count' => 15,
        'needs_survey' => false,
    ])->assertSuccessful();

    expect(Matter::find($id)->payloadValue('needs_survey'))->toBeTrue();
});

test('admins can correct the survey flag through the admin channel', function () {
    $matter = Matter::factory()->survey()->create();
    Sanctum::actingAs(Resident::factory()->admin()->create());

    $this->putJson("/api/admin/matters/{$matter->id}", [
        'title' => $matter->title,
        'category' => $matter->category,
        'target_count' => $matter->target_count,
        'payload' => ['needs_survey' => false],
    ])->assertSuccessful();

    expect($matter->refresh()->payloadValue('needs_survey'))->toBeFalse();
});
