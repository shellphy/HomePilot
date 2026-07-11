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

test('an owner must pick a building before joining but party members are exempt', function () {
    $matter = Matter::factory()->open()->create();

    Sanctum::actingAs(Resident::factory()->withoutUnit()->create());
    $this->postJson("/api/matters/{$matter->id}/join")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('profile');

    // 相关方以入驻身份出现，没有楼栋概念
    Sanctum::actingAs(Resident::factory()->merchant()->create(['unit_label' => '']));
    $this->postJson("/api/matters/{$matter->id}/join")->assertCreated();
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
