<?php

use App\Models\Resident;
use App\Models\Unit;
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

    expect($resident->refresh()->unit->label)->toBe('5栋')
        ->and(Unit::count())->toBe(1);

    // 换楼栋复用已有的户对象
    $this->putJson('/api/me', ['unit_label' => '5栋'])->assertSuccessful();
    expect(Unit::count())->toBe(1);
});

test('the room label is a separate private field and every field can be cleared', function () {
    $resident = Resident::factory()->inUnit('5栋')->create(['room_label' => '', 'wechat_id' => 'old']);
    Sanctum::actingAs($resident);

    $this->putJson('/api/me', ['room_label' => '1801'])
        ->assertSuccessful()
        ->assertJsonPath('data.room_label', '1801')
        ->assertJsonPath('data.unit_label', '5栋');

    // 清空要真的清空：楼栋解绑、房号/微信号归空
    $this->putJson('/api/me', ['unit_label' => '', 'room_label' => '', 'wechat_id' => ''])
        ->assertSuccessful()
        ->assertJsonPath('data.unit_label', '')
        ->assertJsonPath('data.room_label', '');

    $resident->refresh();
    expect($resident->unit_id)->toBeNull()
        ->and($resident->room_label)->toBe('')
        ->and($resident->wechat_id)->toBe('');
});
