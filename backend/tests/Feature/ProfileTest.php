<?php

use App\Models\Resident;
use Laravel\Sanctum\Sanctum;

test('a resident can maintain their own contact profile independently of any census', function () {
    $resident = Resident::factory()->withoutUnit()->create(['wechat_id' => '', 'phone' => '']);
    Sanctum::actingAs($resident);

    $this->putJson('/api/me', [
        'unit_label' => '5栋',
        'wechat_id' => 'laoK-2026',
        'phone' => '13800138000',
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.unit_label', '5栋')
        ->assertJsonPath('data.wechat_id', 'laoK-2026');

    expect($resident->refresh()->unit_label)->toBe('5栋');

    // 楼栋号会规整首尾空格
    $this->putJson('/api/me', ['unit_label' => ' 5栋 '])->assertSuccessful();
    expect($resident->refresh()->unit_label)->toBe('5栋');
});

test('the room label is a separate private field and optional fields can be cleared', function () {
    $resident = Resident::factory()->inUnit('5栋')->create(['room_label' => '', 'wechat_id' => 'old']);
    Sanctum::actingAs($resident);

    $this->putJson('/api/me', ['room_label' => '1801'])
        ->assertSuccessful()
        ->assertJsonPath('data.room_label', '1801')
        ->assertJsonPath('data.unit_label', '5栋');

    // 选填字段清空要真的清空（房号/微信号）
    $this->putJson('/api/me', ['room_label' => '', 'wechat_id' => ''])
        ->assertSuccessful()
        ->assertJsonPath('data.room_label', '');

    $resident->refresh();
    expect($resident->room_label)->toBe('')
        ->and($resident->wechat_id)->toBe('');
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

test('a wechat id can only belong to one resident but resubmitting your own is fine', function () {
    Resident::factory()->create(['wechat_id' => 'laoK-2026']);
    $resident = Resident::factory()->create(['wechat_id' => 'mine']);
    Sanctum::actingAs($resident);

    $this->putJson('/api/me', ['wechat_id' => 'laoK-2026'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('wechat_id');

    // 自己重复提交自己的微信号不算冲突；清空也不受唯一约束影响
    $this->putJson('/api/me', ['wechat_id' => 'mine'])->assertSuccessful();
    $this->putJson('/api/me', ['wechat_id' => ''])->assertSuccessful();
});
