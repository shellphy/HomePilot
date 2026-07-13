<?php

use App\Models\Resident;
use App\Services\WeChat;
use Laravel\Sanctum\Sanctum;

test('a resident can maintain their own profile independently of any census', function () {
    $resident = Resident::factory()->withoutUnit()->create();
    Sanctum::actingAs($resident);

    $this->putJson('/api/me', ['unit_label' => '5栋'])
        ->assertSuccessful()
        ->assertJsonPath('data.unit_label', '5栋');

    expect($resident->refresh()->unit_label)->toBe('5栋');
});

test('the unit label must come from the community building list', function () {
    $resident = Resident::factory()->inUnit('5栋')->create();
    Sanctum::actingAs($resident);

    $this->putJson('/api/me', ['unit_label' => '99栋'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('unit_label');

    expect($resident->refresh()->unit_label)->toBe('5栋');
});

test('the layout label must come from the community layout list and is optional', function () {
    $resident = Resident::factory()->inUnit('5栋')->create(['layout_label' => '']);
    Sanctum::actingAs($resident);

    $this->putJson('/api/me', ['layout_label' => '130㎡'])
        ->assertSuccessful()
        ->assertJsonPath('data.layout_label', '130㎡');
    expect($resident->refresh()->layout_label)->toBe('130㎡');

    $this->putJson('/api/me', ['layout_label' => '999㎡'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('layout_label');

    // 选填，可清空
    $this->putJson('/api/me', ['layout_label' => ''])
        ->assertSuccessful()
        ->assertJsonPath('data.layout_label', '');
});

test('the room label is a separate private field and optional fields can be cleared', function () {
    $resident = Resident::factory()->inUnit('5栋')->create(['room_label' => '']);
    Sanctum::actingAs($resident);

    $this->putJson('/api/me', ['room_label' => '1801'])
        ->assertSuccessful()
        ->assertJsonPath('data.room_label', '1801')
        ->assertJsonPath('data.unit_label', '5栋');

    // 选填字段清空要真的清空
    $this->putJson('/api/me', ['room_label' => ''])
        ->assertSuccessful()
        ->assertJsonPath('data.room_label', '');

    expect($resident->refresh()->room_label)->toBe('');
});

test('an owner cannot clear the unit label but a party member has no such requirement', function () {
    $owner = Resident::factory()->inUnit('5栋')->create();
    Sanctum::actingAs($owner);

    $this->putJson('/api/me', ['unit_label' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('unit_label');

    expect($owner->refresh()->unit_label)->toBe('5栋');

    // 相关方账号没有楼栋概念，允许为空
    $merchant = Resident::factory()->merchant()->create();
    Sanctum::actingAs($merchant);
    $this->putJson('/api/me', ['unit_label' => ''])->assertSuccessful();
});

test('the wechat authorization exchange resolves a number for prefill without persisting it', function () {
    $resident = Resident::factory()->create(['phone' => '']);
    Sanctum::actingAs($resident);

    $this->mock(WeChat::class)
        ->shouldReceive('phoneNumberFromCode')
        ->once()
        ->with('auth-code')
        ->andReturn('13800138000');

    $this->postJson('/api/me/phone', ['code' => 'auth-code'])
        ->assertSuccessful()
        ->assertJsonPath('data.phone', '13800138000');

    // 只解析返回供前端预填，不落库；写入统一走 /me 保存
    expect($resident->refresh()->phone)->toBe('');
});

test('the phone is saved as part of the profile and validated', function () {
    $resident = Resident::factory()->create(['phone' => '']);
    Sanctum::actingAs($resident);

    $this->putJson('/api/me', ['phone' => '13911112222'])
        ->assertSuccessful()
        ->assertJsonPath('data.phone', '13911112222');
    expect($resident->refresh()->phone)->toBe('13911112222');

    // 非法号码被拒
    $this->putJson('/api/me', ['phone' => '139'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('phone');
    expect($resident->refresh()->phone)->toBe('13911112222');

    // 允许清空
    $this->putJson('/api/me', ['phone' => ''])
        ->assertSuccessful()
        ->assertJsonPath('data.phone', '');
    expect($resident->refresh()->phone)->toBe('');
});

test('the avatar is saved through the unified profile update', function () {
    $resident = Resident::factory()->create(['avatar' => '']);
    Sanctum::actingAs($resident);

    $this->putJson('/api/me', ['avatar' => 'https://cdn.example.com/a.png'])
        ->assertSuccessful()
        ->assertJsonPath('data.avatar', 'https://cdn.example.com/a.png');
    expect($resident->refresh()->avatar)->toBe('https://cdn.example.com/a.png');
});

test('the phone exchange requires a code', function () {
    Sanctum::actingAs(Resident::factory()->create());

    $this->postJson('/api/me/phone', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');
});
