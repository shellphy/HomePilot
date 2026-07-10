<?php

use App\Models\Project;
use App\Models\Resident;
use App\Models\Signup;
use Laravel\Sanctum\Sanctum;

test('the project list puts active projects first and finished ones last', function () {
    $done = Project::factory()->done()->create();
    $seeking = Project::factory()->create();
    $open = Project::factory()->open()->create();

    Sanctum::actingAs(Resident::factory()->create());

    $this->getJson('/api/projects')
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $open->id)
        ->assertJsonPath('data.1.id', $seeking->id)
        ->assertJsonPath('data.2.id', $done->id)
        ->assertJsonPath('data.0.status_label', '接龙中');
});

test('the project list carries signup counts', function () {
    $project = Project::factory()->open()->create();
    Signup::factory()->count(3)->for($project)->create();

    Sanctum::actingAs(Resident::factory()->create());

    $this->getJson('/api/projects')
        ->assertSuccessful()
        ->assertJsonPath('data.0.signups_count', 3);
});

test('the project detail shows the roster and whether I joined', function () {
    $project = Project::factory()->open()->create();
    $neighbor = Resident::factory()->create(['unit_label' => '3-2-1801', 'nickname' => '老K']);
    Signup::factory()->for($project)->for($neighbor)->create();

    Sanctum::actingAs(Resident::factory()->create());

    $this->getJson("/api/projects/{$project->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.roster.0', '3-2-1801 老K')
        ->assertJsonPath('joined', false);
});

test('a resident can sign up for a project and the count grows', function () {
    $project = Project::factory()->open()->create();
    Sanctum::actingAs(Resident::factory()->create());

    $this->postJson("/api/projects/{$project->id}/signup")
        ->assertCreated()
        ->assertJsonPath('joined', true)
        ->assertJsonPath('signups_count', 1);
});

test('signing up twice stays idempotent', function () {
    $project = Project::factory()->open()->create();
    Sanctum::actingAs(Resident::factory()->create());

    $this->postJson("/api/projects/{$project->id}/signup")->assertCreated();
    $this->postJson("/api/projects/{$project->id}/signup")
        ->assertCreated()
        ->assertJsonPath('signups_count', 1);

    expect(Signup::count())->toBe(1);
});

test('a resident can cancel their signup', function () {
    $project = Project::factory()->open()->create();
    $resident = Resident::factory()->create();
    Signup::factory()->for($project)->for($resident)->create();
    Sanctum::actingAs($resident);

    $this->deleteJson("/api/projects/{$project->id}/signup")
        ->assertSuccessful()
        ->assertJsonPath('joined', false)
        ->assertJsonPath('signups_count', 0);

    expect(Signup::count())->toBe(0);
});

test('cancelling never touches other residents signups', function () {
    $project = Project::factory()->open()->create();
    $neighbor = Resident::factory()->create();
    Signup::factory()->for($project)->for($neighbor)->create();

    Sanctum::actingAs(Resident::factory()->create());

    $this->deleteJson("/api/projects/{$project->id}/signup")
        ->assertSuccessful()
        ->assertJsonPath('signups_count', 1);

    expect(Signup::count())->toBe(1);
});

test('guests cannot browse projects', function () {
    $this->getJson('/api/projects')->assertUnauthorized();
});
