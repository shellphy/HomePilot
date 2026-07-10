<?php

use App\Models\Project;
use App\Models\Resident;
use Laravel\Sanctum\Sanctum;

test('a resident can switch to a merchant profile', function () {
    $resident = Resident::factory()->create();
    Sanctum::actingAs($resident);

    $this->putJson('/api/me', [
        'role' => 'merchant',
        'merchant_name' => '青城中央空调',
        'merchant_category' => '中央空调',
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.role', 'merchant')
        ->assertJsonPath('data.merchant_name', '青城中央空调');
});

test('merchants can browse projects and stats but cannot act like residents', function () {
    $project = Project::factory()->open()->create();
    $merchant = Resident::factory()->create(['role' => 'merchant']);
    Sanctum::actingAs($merchant);

    // 能看：团购列表、详情、意向统计（这是给商家的价值）
    $this->getJson('/api/projects')->assertSuccessful();
    $this->getJson("/api/projects/{$project->id}")->assertSuccessful();
    $this->getJson('/api/stats')->assertSuccessful();

    // 不能干：报名、登记、发起
    $this->postJson("/api/projects/{$project->id}/signup")->assertForbidden();
    $this->putJson('/api/registration', [
        'layout' => config('homepilot.layouts')[0],
        'decoration_mode' => config('homepilot.decoration_modes')[0],
        'interests' => [config('homepilot.categories')[0]],
        'unit_label' => '1栋',
        'wechat_id' => 'shop-1',
    ])->assertForbidden();
    $this->postJson('/api/projects', [
        'category' => '门窗',
        'title' => '商家自荐团',
        'status' => 'seeking',
        'target_households' => 10,
    ])->assertForbidden();
});
