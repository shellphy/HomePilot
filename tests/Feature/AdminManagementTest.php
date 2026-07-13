<?php

use App\Models\Resident;
use Laravel\Sanctum\Sanctum;

test('a super admin looks up a member by phone then grants admin by confirmed id', function () {
    $superAdmin = Resident::factory()->superAdmin()->create(['nickname' => '张工', 'unit_label' => '1栋']);
    $member = Resident::factory()->create(['phone' => '13800138000', 'nickname' => '老王', 'unit_label' => '3栋']);
    Sanctum::actingAs($superAdmin);

    $this->getJson('/api/admin/admins/candidate?phone=13800138000')
        ->assertSuccessful()
        ->assertJsonPath('data.id', $member->id)
        ->assertJsonPath('data.name', '3栋 老王');

    $this->postJson('/api/admin/admins', ['resident_id' => $member->id])
        ->assertCreated()
        ->assertJsonPath('data.name', '3栋 老王')
        ->assertJsonPath('data.granted_by', '1栋 张工');

    $member->refresh();
    expect($member->is_admin)->toBeTrue()
        ->and($member->admin_granted_by_id)->toBe($superAdmin->id)
        ->and($member->admin_granted_at)->not->toBeNull();
});

test('candidate lookup rejects an unknown phone or an existing admin', function () {
    Sanctum::actingAs(Resident::factory()->superAdmin()->create());

    $this->getJson('/api/admin/admins/candidate?phone=13900000000')
        ->assertStatus(422)
        ->assertJsonValidationErrors('phone');

    $existing = Resident::factory()->admin()->create(['phone' => '13811112222']);
    $this->getJson('/api/admin/admins/candidate?phone=13811112222')
        ->assertStatus(422)
        ->assertJsonValidationErrors('phone');
});

test('granting rejects an unknown id or an existing admin', function () {
    Sanctum::actingAs(Resident::factory()->superAdmin()->create());

    $this->postJson('/api/admin/admins', ['resident_id' => 999999])
        ->assertStatus(422)
        ->assertJsonValidationErrors('resident_id');

    $existing = Resident::factory()->admin()->create();
    $this->postJson('/api/admin/admins', ['resident_id' => $existing->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('resident_id');
    expect($existing->refresh()->is_admin)->toBeTrue();
});

test('a super admin revokes a regular admin but not another super admin', function () {
    Sanctum::actingAs(Resident::factory()->superAdmin()->create());

    $admin = Resident::factory()->admin()->create();
    $this->deleteJson("/api/admin/admins/{$admin->id}")->assertSuccessful();
    expect($admin->refresh()->is_admin)->toBeFalse();

    $otherSuper = Resident::factory()->superAdmin()->create();
    $this->deleteJson("/api/admin/admins/{$otherSuper->id}")->assertStatus(422);
    expect($otherSuper->refresh()->is_admin)->toBeTrue();
});

test('the admin list shows current admins with grant audit', function () {
    $superAdmin = Resident::factory()->superAdmin()->create(['nickname' => '张工', 'unit_label' => '1栋']);
    $granted = Resident::factory()->create(['nickname' => '老王', 'unit_label' => '3栋']);
    $granted->grantAdmin($superAdmin);
    Sanctum::actingAs($superAdmin);

    $this->getJson('/api/admin/admins')
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        // 超管排在前，granted_by 为 null（创始人由 CLI 种下）
        ->assertJsonPath('data.0.is_super_admin', true)
        ->assertJsonPath('data.0.granted_by', null)
        ->assertJsonPath('data.1.name', '3栋 老王')
        ->assertJsonPath('data.1.granted_by', '1栋 张工');
});

test('a regular admin cannot reach admin management', function (string $method, string $uri) {
    Sanctum::actingAs(Resident::factory()->admin()->create());

    $this->json($method, $uri)->assertForbidden();
})->with([
    ['GET', '/api/admin/admins'],
    ['GET', '/api/admin/admins/candidate'],
    ['POST', '/api/admin/admins'],
    ['DELETE', '/api/admin/admins/1'],
]);

test('the admin grant command can seed a super admin', function () {
    $resident = Resident::factory()->create(['phone' => '13800138000']);

    $this->artisan('admin:grant', ['resident' => '13800138000', '--super' => true])->assertSuccessful();

    $resident->refresh();
    expect($resident->is_admin)->toBeTrue()
        ->and($resident->is_super_admin)->toBeTrue();
});
