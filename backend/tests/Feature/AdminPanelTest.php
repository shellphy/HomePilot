<?php

use App\Models\Matter;
use App\Models\Party;
use App\Models\Record;
use App\Models\User;

test('the admin can open the matter list in the admin panel', function () {
    Matter::factory()->open()->create(['title' => '「城建装饰」整装团购']);

    $this->actingAs(User::factory()->create())
        ->get('/admin/matters')
        ->assertSuccessful()
        ->assertSee('「城建装饰」整装团购');
});

test('the admin can open the census record list in the admin panel', function () {
    Record::factory()->censusAnswers()->create();

    $this->actingAs(User::factory()->create())
        ->get('/admin/records')
        ->assertSuccessful();
});

test('the admin can open the party list in the admin panel', function () {
    Party::factory()->merchant()->create(['name' => '青城中央空调']);

    $this->actingAs(User::factory()->create())
        ->get('/admin/parties')
        ->assertSuccessful()
        ->assertSee('青城中央空调');
});

test('the admin can open the matter create form with the census schema builder', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin/matters/create')
        ->assertSuccessful()
        ->assertSee('征集问卷');
});

test('the admin can open the community settings page', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin/community-settings')
        ->assertSuccessful()
        ->assertSee('社区设置')
        ->assertSee('小区名称');
});

test('guests are redirected away from the admin panel', function () {
    $this->get('/admin/matters')->assertRedirect();
});
