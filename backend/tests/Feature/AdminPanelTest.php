<?php

use App\Models\Project;
use App\Models\Registration;
use App\Models\User;

test('the leader can open the project list in the admin panel', function () {
    Project::factory()->open()->create(['title' => '「城建装饰」整装团购']);

    $this->actingAs(User::factory()->create())
        ->get('/admin/projects')
        ->assertSuccessful()
        ->assertSee('「城建装饰」整装团购');
});

test('the leader can open the registration list in the admin panel', function () {
    Registration::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get('/admin/registrations')
        ->assertSuccessful();
});

test('guests are redirected away from the admin panel', function () {
    $this->get('/admin/projects')->assertRedirect();
});
