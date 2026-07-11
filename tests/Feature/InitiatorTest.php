<?php

use App\Models\Matter;
use App\Models\Resident;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

test('any resident can initiate a groupbuy which starts unapproved', function () {
    Sanctum::actingAs(Resident::factory()->create());

    $response = $this->postJson('/api/matters', [
        'type' => 'groupbuy',
        'category' => '门窗',
        'title' => '门窗团购（断桥铝）',
        'target_count' => 15,
    ])
        ->assertCreated()
        ->assertJsonPath('data.is_approved', false)
        ->assertJsonPath('data.state', 'seeking');

    expect(Matter::find($response->json('data.id'))->is_approved)->toBeFalse();
});

test('the initial state comes from the state machine and ignores client input', function () {
    Sanctum::actingAs(Resident::factory()->create());

    $this->postJson('/api/matters', [
        'type' => 'groupbuy',
        'category' => '门窗',
        'title' => '门窗团购（断桥铝）',
        'target_count' => 15,
        'state' => 'done',
    ])
        ->assertCreated()
        ->assertJsonPath('data.state', 'seeking');
});

test('residents cannot initiate admin-only matter types like notices', function () {
    Sanctum::actingAs(Resident::factory()->create());

    $this->postJson('/api/matters', [
        'type' => 'notice',
        'title' => '假公告',
        'body' => '业主不能发公告',
    ])->assertForbidden();
});

test('unapproved matters stay out of the public feed but show up in mine', function () {
    $initiator = Resident::factory()->create();
    $pending = Matter::factory()->pending()->for($initiator, 'initiator')->create();
    $approved = Matter::factory()->open()->create();

    Sanctum::actingAs($initiator);

    $this->getJson('/api/matters')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $approved->id);

    $this->getJson('/api/matters/mine')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $pending->id);
});

test('an unapproved matter detail is hidden from everyone but the initiator', function () {
    $initiator = Resident::factory()->create();
    $pending = Matter::factory()->pending()->for($initiator, 'initiator')->create();

    Sanctum::actingAs(Resident::factory()->create());
    $this->getJson("/api/matters/{$pending->id}")->assertNotFound();

    Sanctum::actingAs($initiator);
    $this->getJson("/api/matters/{$pending->id}")->assertSuccessful();
});

test('only the initiator can edit a matter', function () {
    $initiator = Resident::factory()->create();
    $matter = Matter::factory()->for($initiator, 'initiator')->create();

    $payload = [
        'category' => $matter->category,
        'title' => $matter->title,
        'state' => 'open',
        'target_count' => 20,
    ];

    Sanctum::actingAs(Resident::factory()->create());
    $this->putJson("/api/matters/{$matter->id}", $payload)->assertForbidden();

    Sanctum::actingAs($initiator);
    $this->putJson("/api/matters/{$matter->id}", $payload)
        ->assertSuccessful()
        ->assertJsonPath('data.state', 'open');
});

test('editing keeps payload fields that are not part of the form', function () {
    $initiator = Resident::factory()->create();
    $matter = Matter::factory()->done()->for($initiator, 'initiator')->create();
    $matter->update(['payload' => array_merge($matter->payload, ['final_note' => '返点已让利'])]);

    Sanctum::actingAs($initiator);
    $this->putJson("/api/matters/{$matter->id}", [
        'category' => $matter->category,
        'title' => '改了标题',
        'target_count' => 30,
    ])->assertSuccessful();

    expect($matter->refresh()->payload['final_note'])->toBe('返点已让利');
});

test('only the initiator can flip the state through the state endpoint', function () {
    $initiator = Resident::factory()->create();
    $matter = Matter::factory()->for($initiator, 'initiator')->create();

    Sanctum::actingAs(Resident::factory()->create());
    $this->putJson("/api/matters/{$matter->id}/state", ['state' => 'open'])->assertForbidden();

    Sanctum::actingAs($initiator);
    $this->putJson("/api/matters/{$matter->id}/state", ['state' => 'flying'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('state');

    $this->putJson("/api/matters/{$matter->id}/state", ['state' => 'open'])
        ->assertSuccessful()
        ->assertJsonPath('data.state', 'open');

    expect($matter->refresh()->state)->toBe('open');
});

test('only the initiator can post timeline updates', function () {
    $initiator = Resident::factory()->create();
    $matter = Matter::factory()->open()->for($initiator, 'initiator')->create();

    $payload = [
        'happened_on' => '2026-07-10',
        'content' => '水电开槽完成',
        'images' => ['http://localhost:8000/storage/progress/a.jpg'],
    ];

    Sanctum::actingAs(Resident::factory()->create());
    $this->postJson("/api/matters/{$matter->id}/updates", $payload)->assertForbidden();

    Sanctum::actingAs($initiator);
    $this->postJson("/api/matters/{$matter->id}/updates", $payload)->assertCreated();

    expect($matter->updates()->count())->toBe(1);
});

test('the detail carries the initiator display name', function () {
    $initiator = Resident::factory()->inUnit('3栋')->create(['nickname' => '老K']);
    $matter = Matter::factory()->open()->for($initiator, 'initiator')->create();

    Sanctum::actingAs(Resident::factory()->create());

    $this->getJson("/api/matters/{$matter->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.initiator_name', '3栋 老K');
});

test('an authenticated resident can upload a progress photo', function () {
    Storage::fake('public');
    Sanctum::actingAs(Resident::factory()->create());

    $response = $this->post('/api/uploads', [
        'image' => UploadedFile::fake()->image('site.jpg'),
    ], ['Accept' => 'application/json'])->assertCreated();

    expect($response->json('url'))->toContain('/storage/uploads/');
});

test('matter validation rejects a bad state and an unknown type', function () {
    $initiator = Resident::factory()->create();
    $matter = Matter::factory()->for($initiator, 'initiator')->create();
    Sanctum::actingAs($initiator);

    $this->putJson("/api/matters/{$matter->id}", [
        'category' => '门窗',
        'title' => '测试',
        'state' => 'flying',
        'target_count' => 10,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('state');

    $this->postJson('/api/matters', [
        'type' => 'carpool',
        'title' => '拼车',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('type');
});
