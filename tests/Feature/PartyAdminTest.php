<?php

use App\Models\Matter;
use App\Models\Party;
use App\Models\Resident;
use App\Settings\CommunitySettings;
use Laravel\Sanctum\Sanctum;

test('party admin routes reject ordinary members', function (string $method, string $uriTemplate) {
    $resident = Resident::factory()->create();
    $party = Party::factory()->create();
    Sanctum::actingAs($resident);

    $uri = str_replace(['{party}', '{resident}'], [$party->id, $resident->id], $uriTemplate);

    $this->json($method, $uri)->assertForbidden();
})->with([
    ['POST', '/api/admin/parties'],
    ['POST', '/api/admin/parties/{party}/members'],
    ['DELETE', '/api/admin/parties/{party}/members/{resident}'],
]);

test('admins can create a governance party which is listed by default', function () {
    Sanctum::actingAs(Resident::factory()->admin()->create());

    $this->postJson('/api/admin/parties', ['type' => 'property', 'name' => '天青府物业服务中心'])
        ->assertCreated()
        ->assertJsonPath('data.type_label', '物业')
        ->assertJsonPath('data.is_listed', true);
});

test('admins can bind members by phone or id and unbind them back to owners', function () {
    Sanctum::actingAs(Resident::factory()->admin()->create());
    $party = Party::factory()->listed()->create(['type' => Party::TYPE_PROPERTY]);
    $byPhone = Resident::factory()->create(['phone' => '13900139000']);
    $byId = Resident::factory()->create();

    $this->postJson("/api/admin/parties/{$party->id}/members", ['resident' => '13900139000'])
        ->assertSuccessful()
        ->assertJsonPath('data.members_count', 1);

    $this->postJson("/api/admin/parties/{$party->id}/members", ['resident' => (string) $byId->id])
        ->assertSuccessful()
        ->assertJsonPath('data.members_count', 2);

    expect($byPhone->refresh()->affiliated_party_id)->toBe($party->id)
        ->and($byId->refresh()->affiliated_party_id)->toBe($party->id);

    $this->deleteJson("/api/admin/parties/{$party->id}/members/{$byPhone->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.members_count', 1);

    expect($byPhone->refresh()->affiliated_party_id)->toBeNull();
});

test('a bound governance member can post official responses', function () {
    Sanctum::actingAs(Resident::factory()->admin()->create());
    $party = Party::factory()->listed()->create(['type' => Party::TYPE_PROPERTY, 'name' => '天青府物业服务中心']);
    $member = Resident::factory()->create();

    $this->postJson("/api/admin/parties/{$party->id}/members", ['resident' => (string) $member->id])
        ->assertSuccessful();

    $matter = Matter::factory()->rights()->create();
    Sanctum::actingAs($member->refresh());

    $this->postJson("/api/matters/{$matter->id}/updates", [
        'happened_on' => '2026-07-11',
        'content' => '已收到联名诉求，本周五前给出书面答复。',
    ])->assertCreated()->assertJsonPath('data.author', '物业 · 天青府物业服务中心');
});

test('binding an unknown member returns a validation error', function () {
    Sanctum::actingAs(Resident::factory()->admin()->create());
    $party = Party::factory()->create();

    $this->postJson("/api/admin/parties/{$party->id}/members", ['resident' => '13000000000'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('resident');
});

test('unbinding a member who belongs to another party is a 404', function () {
    Sanctum::actingAs(Resident::factory()->admin()->create());
    $party = Party::factory()->create();
    $stranger = Resident::factory()->merchant()->create();

    $this->deleteJson("/api/admin/parties/{$party->id}/members/{$stranger->id}")->assertNotFound();
});

test('the pending count tracks unlisted parties that have members', function () {
    Sanctum::actingAs(Resident::factory()->admin()->create());
    Party::factory()->create(); // 空壳档案不算待办
    Party::factory()->listed()->create();
    Resident::factory()->merchant()->create(); // merchant() 绑定一个未认证商家

    $this->getJson('/api/admin/parties')
        ->assertSuccessful()
        ->assertJsonPath('pending_count', 1);
});

test('the party list carries member details for the admin page', function () {
    Sanctum::actingAs(Resident::factory()->admin()->create());
    $member = Resident::factory()->inUnit('5栋')->merchant()->create(['nickname' => '王老板']);

    $this->getJson('/api/admin/parties')
        ->assertJsonPath('data.0.members.0.id', $member->id)
        ->assertJsonPath('data.0.members.0.nickname', '王老板')
        ->assertJsonPath('data.0.members.0.unit_label', '5栋');
});

test('options expose the admin contact for the certification guide', function () {
    $this->getJson('/api/options')
        ->assertSuccessful()
        ->assertJsonPath('community.admin_contact', app(CommunitySettings::class)->admin_contact);
});

test('admins can update the admin contact in settings', function () {
    Sanctum::actingAs(Resident::factory()->admin()->create());
    $payload = array_merge(app(CommunitySettings::class)->toArray(), [
        'admin_contact' => '微信 tqf-admin，或到 8 栋物业前台',
    ]);

    $this->putJson('/api/admin/settings', $payload)
        ->assertSuccessful()
        ->assertJsonPath('data.admin_contact', '微信 tqf-admin，或到 8 栋物业前台');

    $this->getJson('/api/options')
        ->assertJsonPath('community.admin_contact', '微信 tqf-admin，或到 8 栋物业前台');
});
