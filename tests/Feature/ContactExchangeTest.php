<?php

use App\Models\Matter;
use App\Models\Resident;
use App\Models\Stance;
use Laravel\Sanctum\Sanctum;

test('joining stores the contact sharing consent and rejoining updates it', function () {
    $matter = Matter::factory()->open()->create();
    Sanctum::actingAs(Resident::factory()->create());

    $this->postJson("/api/matters/{$matter->id}/join", ['share_contact' => false])->assertCreated();

    $stance = Stance::where('mode', Stance::MODE_JOIN)->sole();
    expect($stance->payload['share_contact'])->toBeFalse();

    // 重新报名可改共享意愿，不会产生第二条表态
    $this->postJson("/api/matters/{$matter->id}/join", ['share_contact' => true])->assertCreated();
    expect(Stance::where('mode', Stance::MODE_JOIN)->count())->toBe(1)
        ->and($stance->refresh()->payload['share_contact'])->toBeTrue();
});

test('a participant can still revise the sharing consent after the deal closes but nobody new can join', function () {
    $matter = Matter::factory()->done()->create();
    $participant = Resident::factory()->create();
    Stance::factory()->for($matter, 'matter')->for($participant, 'resident')->create(['payload' => ['share_contact' => false]]);

    // 已在名单里：成团后补开共享，才能与团长互见电话
    Sanctum::actingAs($participant);
    $this->postJson("/api/matters/{$matter->id}/join", ['share_contact' => true])->assertCreated();
    expect(Stance::where('mode', Stance::MODE_JOIN)->sole()->payload['share_contact'])->toBeTrue();

    // 不在名单里的：成团后不能再加入
    Sanctum::actingAs(Resident::factory()->create());
    $this->postJson("/api/matters/{$matter->id}/join")->assertUnprocessable();
    expect(Stance::where('mode', Stance::MODE_JOIN)->count())->toBe(1);
});

test('an owner must pick a building before joining', function () {
    $matter = Matter::factory()->open()->create();

    Sanctum::actingAs(Resident::factory()->withoutUnit()->create());
    $this->postJson("/api/matters/{$matter->id}/join")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('profile');
});

test('once the deal is done the initiator sees only consenting participants with a phone', function () {
    $initiator = Resident::factory()->create(['phone' => '13900000000']);
    $matter = Matter::factory()->done()->for($initiator, 'initiator')->create();

    $consenting = Resident::factory()->inUnit('5栋')->create(['nickname' => '老王', 'phone' => '13811112222']);
    $noPhone = Resident::factory()->create(['phone' => '']);
    $declined = Resident::factory()->create(['phone' => '13833334444']);

    Stance::factory()->for($matter, 'matter')->for($consenting, 'resident')->create(['payload' => ['share_contact' => true]]);
    Stance::factory()->for($matter, 'matter')->for($noPhone, 'resident')->create(['payload' => ['share_contact' => true]]);
    Stance::factory()->for($matter, 'matter')->for($declined, 'resident')->create(['payload' => ['share_contact' => false]]);

    Sanctum::actingAs($initiator);

    $response = $this->getJson("/api/matters/{$matter->id}")->assertSuccessful();

    expect($response->json('contacts'))->toBe([['name' => '5栋 老王', 'phone' => '13811112222']]);
});

test('contacts stay closed before the deal is done', function () {
    $initiator = Resident::factory()->create(['phone' => '13900000000']);
    $matter = Matter::factory()->open()->for($initiator, 'initiator')->create();
    $participant = Resident::factory()->create(['phone' => '13811112222']);
    Stance::factory()->for($matter, 'matter')->for($participant, 'resident')->create(['payload' => ['share_contact' => true]]);

    Sanctum::actingAs($initiator);
    expect($this->getJson("/api/matters/{$matter->id}")->json('contacts'))->toBe([]);

    Sanctum::actingAs($participant);
    expect($this->getJson("/api/matters/{$matter->id}")->json('initiator_contact'))->toBeNull();
});

// ---- 活动/互助：协调发生在事前，报名中/进行中就互通（发起人 ↔ 同意共享的参与者） ----

test('activities and aids exchange contacts with the initiator while active', function (string $factoryState) {
    $initiator = Resident::factory()->inUnit('3栋')->create(['nickname' => '老K', 'phone' => '13900000000']);
    $matter = Matter::factory()->{$factoryState}()->for($initiator, 'initiator')->create();

    $consenting = Resident::factory()->inUnit('5栋')->create(['nickname' => '老王', 'phone' => '13811112222']);
    $declined = Resident::factory()->create(['phone' => '13833334444']);
    Stance::factory()->for($matter, 'matter')->for($consenting, 'resident')->create(['payload' => ['share_contact' => true]]);
    Stance::factory()->for($matter, 'matter')->for($declined, 'resident')->create(['payload' => ['share_contact' => false]]);

    Sanctum::actingAs($initiator);
    expect($this->getJson("/api/matters/{$matter->id}")->json('contacts'))
        ->toBe([['name' => '5栋 老王', 'phone' => '13811112222']]);

    Sanctum::actingAs($consenting);
    $this->getJson("/api/matters/{$matter->id}")
        ->assertJsonPath('data.contacts_open', true)
        ->assertJsonPath('initiator_contact.name', '3栋 老K')
        ->assertJsonPath('initiator_contact.phone', '13900000000');

    Sanctum::actingAs($declined);
    $this->getJson("/api/matters/{$matter->id}")->assertJsonPath('initiator_contact', null);
})->with(['activity', 'aid']);

test('activity contacts close once it ends or is cancelled', function (string $state) {
    $initiator = Resident::factory()->create(['phone' => '13900000000']);
    $matter = Matter::factory()->activity()->for($initiator, 'initiator')->create(['state' => $state]);
    $participant = Resident::factory()->create(['phone' => '13811112222']);
    Stance::factory()->for($matter, 'matter')->for($participant, 'resident')->create(['payload' => ['share_contact' => true]]);

    Sanctum::actingAs($initiator);
    expect($this->getJson("/api/matters/{$matter->id}")->json('contacts'))->toBe([]);

    Sanctum::actingAs($participant);
    $this->getJson("/api/matters/{$matter->id}")
        ->assertJsonPath('data.contacts_open', false)
        ->assertJsonPath('initiator_contact', null);
})->with(['done', 'aborted']);

test('rights actions never exchange contacts even while collecting', function () {
    $initiator = Resident::factory()->create(['phone' => '13900000000']);
    $matter = Matter::factory()->rights()->for($initiator, 'initiator')->create();
    $signer = Resident::factory()->create(['phone' => '13811112222']);
    Stance::factory()->for($matter, 'matter')->for($signer, 'resident')->create(['payload' => ['share_contact' => true]]);

    Sanctum::actingAs($initiator);
    expect($this->getJson("/api/matters/{$matter->id}")->json('contacts'))->toBe([]);

    Sanctum::actingAs($signer);
    $this->getJson("/api/matters/{$matter->id}")
        ->assertJsonPath('data.contacts_open', false)
        ->assertJsonPath('initiator_contact', null);
});

test('a consenting participant sees the initiator contact after the deal, a declining one does not', function () {
    $initiator = Resident::factory()->inUnit('3栋')->create(['nickname' => '老K', 'phone' => '13900000000']);
    $matter = Matter::factory()->done()->for($initiator, 'initiator')->create();

    $consenting = Resident::factory()->create(['phone' => '13811112222']);
    $declined = Resident::factory()->create(['phone' => '13833334444']);
    Stance::factory()->for($matter, 'matter')->for($consenting, 'resident')->create(['payload' => ['share_contact' => true]]);
    Stance::factory()->for($matter, 'matter')->for($declined, 'resident')->create(['payload' => ['share_contact' => false]]);

    Sanctum::actingAs($consenting);
    $this->getJson("/api/matters/{$matter->id}")
        ->assertJsonPath('initiator_contact.name', '3栋 老K')
        ->assertJsonPath('initiator_contact.phone', '13900000000')
        ->assertJsonPath('my_share_contact', true);

    Sanctum::actingAs($declined);
    $this->getJson("/api/matters/{$matter->id}")
        ->assertJsonPath('initiator_contact', null)
        ->assertJsonPath('my_share_contact', false);
});
