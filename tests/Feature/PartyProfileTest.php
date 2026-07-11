<?php

use App\Models\Party;
use App\Models\Resident;
use Laravel\Sanctum\Sanctum;

test('registration saves the unified profile fields and echoes them back in me', function () {
    Sanctum::actingAs(Resident::factory()->create());

    $this->postJson('/api/me/party', [
        'type' => 'property',
        'name' => '天青府物业服务中心',
        'intro' => '报修、投诉、公共区域问题都可以找我们',
        'description' => "服务时间：每天 8:00-20:00\n报修电话见前台公示",
        'images' => ['https://example.com/a.jpg', 'https://example.com/b.jpg'],
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.party.intro', '报修、投诉、公共区域问题都可以找我们')
        ->assertJsonPath('data.party.images.1', 'https://example.com/b.jpg');

    expect(Party::first()->description)->toContain('服务时间');
});

test('re-registering updates the profile on the same party record', function () {
    $resident = Resident::factory()->create();
    Sanctum::actingAs($resident);

    $this->postJson('/api/me/party', ['type' => 'merchant', 'name' => '青城门窗', 'intro' => '老简介'])->assertSuccessful();
    $this->postJson('/api/me/party', ['type' => 'merchant', 'name' => '青城门窗', 'intro' => '店在红星美凯龙 3 楼'])->assertSuccessful();

    expect(Party::count())->toBe(1)
        ->and(Party::first()->intro)->toBe('店在红星美凯龙 3 楼');
});

test('the directory list carries the intro line', function () {
    Party::factory()->listed()->merchant()->create(['name' => '青城门窗', 'intro' => '主做断桥铝，店在建材城']);
    Sanctum::actingAs(Resident::factory()->create());

    $this->getJson('/api/parties')
        ->assertSuccessful()
        ->assertJsonPath('data.0.intro', '主做断桥铝，店在建材城');
});

test('a listed party detail is visible to everyone with profile and stats', function () {
    $party = Party::factory()->listed()->merchant()->create([
        'name' => '青城门窗',
        'intro' => '主做断桥铝',
        'description' => '店面地址：建材城 3 楼',
        'images' => ['https://example.com/a.jpg'],
    ]);
    $owner = Resident::factory()->create(['affiliated_party_id' => $party->id, 'last_party_id' => $party->id]);

    Sanctum::actingAs(Resident::factory()->create());

    $this->getJson("/api/parties/{$party->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.name', '青城门窗')
        ->assertJsonPath('data.intro', '主做断桥铝')
        ->assertJsonPath('data.description', '店面地址：建材城 3 楼')
        ->assertJsonPath('data.images.0', 'https://example.com/a.jpg')
        ->assertJsonPath('data.phone', $owner->phone)
        ->assertJsonPath('data.is_listed', true);
});

test('an unlisted party detail is hidden from ordinary members but visible to admins and its owner', function () {
    $party = Party::factory()->create(['type' => Party::TYPE_PROPERTY]);
    $owner = Resident::factory()->create(['affiliated_party_id' => $party->id, 'last_party_id' => $party->id]);

    Sanctum::actingAs(Resident::factory()->create());
    $this->getJson("/api/parties/{$party->id}")->assertNotFound();

    Sanctum::actingAs(Resident::factory()->admin()->create());
    $this->getJson("/api/parties/{$party->id}")->assertSuccessful()->assertJsonPath('data.is_listed', false);

    Sanctum::actingAs($owner);
    $this->getJson("/api/parties/{$party->id}")->assertSuccessful();

    // 归属人切回业主后依然能预览自己的档案（last_party_id 记着）
    $owner->update(['affiliated_party_id' => null]);
    Sanctum::actingAs($owner->fresh());
    $this->getJson("/api/parties/{$party->id}")->assertSuccessful();
});

test('options carry the description hint per party type', function () {
    $this->getJson('/api/options')
        ->assertSuccessful()
        ->assertJsonPath('party_types.0.key', 'merchant')
        ->assertJsonPath('party_types.1.description_hint', fn (string $hint) => str_contains($hint, '物业'));
});
