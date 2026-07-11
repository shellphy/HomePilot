<?php

use App\Models\Matter;
use App\Models\Resident;
use App\Models\Stance;
use Laravel\Sanctum\Sanctum;

function joinedResident(Matter $matter): Resident
{
    $resident = Resident::factory()->create();
    Stance::factory()->for($matter, 'matter')->for($resident, 'resident')->create();

    return $resident;
}

test('a participant can review a finished groupbuy', function () {
    $matter = Matter::factory()->done()->create();
    $resident = joinedResident($matter);
    Sanctum::actingAs($resident);

    $this->putJson("/api/matters/{$matter->id}/review", ['rating' => 5, 'content' => '师傅手艺不错'])
        ->assertCreated()
        ->assertJsonPath('data.rating', 5);

    expect(Stance::where('mode', Stance::MODE_REVIEW)->count())->toBe(1);
});

test('re-reviewing revises the existing review and keeps the trail', function () {
    $matter = Matter::factory()->done()->create();
    $resident = joinedResident($matter);
    Sanctum::actingAs($resident);

    $this->putJson("/api/matters/{$matter->id}/review", ['rating' => 5])->assertCreated();
    $this->putJson("/api/matters/{$matter->id}/review", ['rating' => 3, 'content' => '后来打胶有点毛糙'])
        ->assertSuccessful();

    $review = Stance::where('mode', Stance::MODE_REVIEW)->first();

    expect(Stance::where('mode', Stance::MODE_REVIEW)->count())->toBe(1)
        ->and($review->payload['rating'])->toBe(3)
        ->and($review->revisions()->count())->toBe(1)
        ->and($review->revisions()->first()->payload['rating'])->toBe(5);
});

test('non participants cannot review', function () {
    $matter = Matter::factory()->done()->create();
    Sanctum::actingAs(Resident::factory()->create());

    $this->putJson("/api/matters/{$matter->id}/review", ['rating' => 5])->assertForbidden();
});

test('reviews are rejected before the groupbuy is done', function () {
    $matter = Matter::factory()->open()->create();
    $resident = joinedResident($matter);
    Sanctum::actingAs($resident);

    $this->putJson("/api/matters/{$matter->id}/review", ['rating' => 5])->assertUnprocessable();
});

test('the matter detail carries reviews and my review', function () {
    $matter = Matter::factory()->done()->create();
    $resident = Resident::factory()->inUnit('3栋')->create(['nickname' => '老K']);
    Stance::factory()->for($matter, 'matter')->for($resident, 'resident')->create();
    Stance::factory()->review(4, '整体满意')->for($matter, 'matter')->for($resident, 'resident')->create();

    Sanctum::actingAs($resident);

    $this->getJson("/api/matters/{$matter->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.reviews.0.rating', 4)
        ->assertJsonPath('data.reviews.0.reviewer_name', '3栋 老K')
        ->assertJsonPath('my_review.rating', 4);
});

test('only the initiator can publish the deal disclosure and only when done', function () {
    $initiator = Resident::factory()->create();
    $matter = Matter::factory()->done()->for($initiator, 'initiator')->create();

    $payload = [
        'final_terms' => [['label' => '最终单价', 'value' => '588 元/㎡']],
        'final_note' => '商家返点 2% 已全部转为参团业主让利，每户减 300 元。',
    ];

    Sanctum::actingAs(Resident::factory()->create());
    $this->putJson("/api/matters/{$matter->id}/deal", $payload)->assertForbidden();

    Sanctum::actingAs($initiator);
    $this->putJson("/api/matters/{$matter->id}/deal", $payload)
        ->assertSuccessful()
        ->assertJsonPath('data.final_terms.0.value', '588 元/㎡');

    $pending = Matter::factory()->open()->for($initiator, 'initiator')->create();
    $this->putJson("/api/matters/{$pending->id}/deal", $payload)->assertUnprocessable();
});
