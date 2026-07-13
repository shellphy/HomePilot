<?php

use App\Enums\MatterReviewStatus;
use App\Models\Matter;
use App\Models\Resident;
use Laravel\Sanctum\Sanctum;

test('a new census stays a draft and out of the review queue until it has a question', function () {
    $initiator = Resident::factory()->admin()->create();
    Sanctum::actingAs($initiator);

    $response = $this->postJson('/api/matters', [
        'type' => 'census',
        'title' => '暑期活动征集',
        'pitch' => '看看大家想参加什么活动',
    ])
        ->assertCreated()
        ->assertJsonPath('data.review_status', 'draft');

    $matter = Matter::findOrFail($response->json('data.id'));

    $this->getJson('/api/admin/matters?pending=1')
        ->assertSuccessful()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('pending_count', 0);

    $this->postJson("/api/matters/{$matter->id}/submit-review")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('modules');

    expect($matter->refresh()->review_status)->toBe(MatterReviewStatus::Draft);
});

test('a census enters the review queue only after its initiator submits a questionnaire with a question', function () {
    $initiator = Resident::factory()->admin()->create();
    $matter = Matter::factory()->draft()->for($initiator, 'initiator')->create([
        'type' => 'census',
        'state' => 'open',
        'payload' => [
            'pitch' => '看看大家想参加什么活动',
            'modules' => [[
                'key' => 'activities',
                'title' => '活动偏好',
                'questions' => [[
                    'key' => 'activity_type',
                    'text' => '你想参加哪种活动？',
                    'type' => 'single',
                    'options' => ['亲子', '运动'],
                ]],
            ]],
        ],
    ]);

    Sanctum::actingAs(Resident::factory()->create());
    $this->postJson("/api/matters/{$matter->id}/submit-review")->assertForbidden();

    Sanctum::actingAs($initiator);
    $this->postJson("/api/matters/{$matter->id}/submit-review")
        ->assertSuccessful()
        ->assertJsonPath('data.review_status', 'pending');

    $this->getJson('/api/admin/matters?pending=1')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matter->id);
});

test('an administrator cannot approve an unfinished census draft', function () {
    $matter = Matter::factory()->draft()->create([
        'type' => 'census',
        'state' => 'open',
        'payload' => ['modules' => []],
    ]);
    Sanctum::actingAs(Resident::factory()->admin()->create());

    $this->putJson("/api/admin/matters/{$matter->id}/approve", ['is_approved' => true])
        ->assertUnprocessable();

    expect($matter->refresh()->review_status)->toBe(MatterReviewStatus::Draft);
});

test('the migration restores existing unfinished censuses as resumable drafts', function () {
    $unfinished = Matter::factory()->pending()->create([
        'type' => 'census',
        'state' => 'open',
        'payload' => ['modules' => [['title' => '还没写完', 'questions' => []]]],
    ]);
    $complete = Matter::factory()->pending()->create([
        'type' => 'census',
        'state' => 'open',
        'payload' => ['modules' => [[
            'title' => '已完成',
            'questions' => [['text' => '问题', 'type' => 'text']],
        ]]],
    ]);

    $migration = require database_path('migrations/2026_07_13_062224_move_incomplete_pending_censuses_to_draft.php');
    $migration->up();

    expect($unfinished->refresh()->review_status)->toBe(MatterReviewStatus::Draft)
        ->and($complete->refresh()->review_status)->toBe(MatterReviewStatus::Pending);
});
