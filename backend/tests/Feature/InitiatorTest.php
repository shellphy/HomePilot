<?php

use App\Models\Project;
use App\Models\Resident;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

test('any resident can initiate a group buy which starts unapproved', function () {
    Sanctum::actingAs(Resident::factory()->create());

    $response = $this->postJson('/api/projects', [
        'category' => '封窗阳台',
        'title' => '封窗阳台团购（断桥铝）',
        'status' => 'seeking',
        'target_households' => 15,
    ])
        ->assertCreated()
        ->assertJsonPath('data.is_approved', false);

    expect(Project::find($response->json('data.id'))->is_approved)->toBeFalse();
});

test('unapproved projects stay out of the public list but show up in mine', function () {
    $initiator = Resident::factory()->create();
    $pending = Project::factory()->pending()->for($initiator, 'initiator')->create();
    $approved = Project::factory()->open()->create();

    Sanctum::actingAs($initiator);

    $this->getJson('/api/projects')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $approved->id);

    $this->getJson('/api/projects/mine')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $pending->id);
});

test('an unapproved project detail is hidden from everyone but the initiator', function () {
    $initiator = Resident::factory()->create();
    $pending = Project::factory()->pending()->for($initiator, 'initiator')->create();

    Sanctum::actingAs(Resident::factory()->create());
    $this->getJson("/api/projects/{$pending->id}")->assertNotFound();

    Sanctum::actingAs($initiator);
    $this->getJson("/api/projects/{$pending->id}")->assertSuccessful();
});

test('only the initiator can edit a project', function () {
    $initiator = Resident::factory()->create();
    $project = Project::factory()->for($initiator, 'initiator')->create();

    $payload = [
        'category' => $project->category,
        'title' => $project->title,
        'status' => 'open',
        'target_households' => 20,
    ];

    Sanctum::actingAs(Resident::factory()->create());
    $this->putJson("/api/projects/{$project->id}", $payload)->assertForbidden();

    Sanctum::actingAs($initiator);
    $this->putJson("/api/projects/{$project->id}", $payload)
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'open');
});

test('only the initiator can post progress updates', function () {
    $initiator = Resident::factory()->create();
    $project = Project::factory()->open()->for($initiator, 'initiator')->create();

    $payload = [
        'happened_on' => '2026-07-10',
        'content' => '水电开槽完成',
        'images' => ['http://localhost:8000/storage/progress/a.jpg'],
    ];

    Sanctum::actingAs(Resident::factory()->create());
    $this->postJson("/api/projects/{$project->id}/progress", $payload)->assertForbidden();

    Sanctum::actingAs($initiator);
    $this->postJson("/api/projects/{$project->id}/progress", $payload)->assertCreated();

    expect($project->progressUpdates()->count())->toBe(1);
});

test('the detail carries the initiator display name', function () {
    $initiator = Resident::factory()->create(['unit_label' => '3-2-1801', 'nickname' => '老K']);
    $project = Project::factory()->open()->for($initiator, 'initiator')->create();

    Sanctum::actingAs(Resident::factory()->create());

    $this->getJson("/api/projects/{$project->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.initiator_name', '3-2-1801 老K');
});

test('an authenticated resident can upload a progress photo', function () {
    Storage::fake('public');
    Sanctum::actingAs(Resident::factory()->create());

    $response = $this->post('/api/uploads', [
        'image' => UploadedFile::fake()->image('site.jpg'),
    ], ['Accept' => 'application/json'])->assertCreated();

    expect($response->json('url'))->toContain('/storage/uploads/');
});

test('project validation rejects a bad status', function () {
    Sanctum::actingAs(Resident::factory()->create());

    $this->postJson('/api/projects', [
        'category' => '封窗阳台',
        'title' => '测试',
        'status' => 'flying',
        'target_households' => 10,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});
