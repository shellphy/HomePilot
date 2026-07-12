<?php

use App\Enums\MatterReviewStatus;
use App\Models\Matter;
use App\Models\Resident;
use App\Models\Stance;
use Laravel\Sanctum\Sanctum;

function attachedCensus(Matter $groupbuy, array $overrides = []): Matter
{
    return Matter::factory()->create(array_merge([
        'type' => 'census',
        'state' => 'open',
        'title' => '中央空调需求摸底',
        'related_matter_id' => $groupbuy->id,
        'payload' => ['pitch' => '答几题帮团长谈判', 'modules' => []],
    ], $overrides));
}

test('admin publishes a census attached to a groupbuy', function () {
    Sanctum::actingAs(Resident::factory()->admin()->create());
    $groupbuy = Matter::factory()->create();

    $response = $this->postJson('/api/admin/matters', [
        'type' => 'census',
        'title' => '中央空调需求摸底',
        'related_matter_id' => $groupbuy->id,
    ])->assertCreated();

    expect(Matter::find($response->json('data.id'))->related_matter_id)->toBe($groupbuy->id);
});

test('a census can only attach to a groupbuy', function () {
    Sanctum::actingAs(Resident::factory()->admin()->create());
    $notice = Matter::factory()->notice()->create();

    $this->postJson('/api/admin/matters', [
        'type' => 'census',
        'title' => '挂到公告上',
        'related_matter_id' => $notice->id,
    ])->assertJsonValidationErrors(['related_matter_id']);
});

test('admin can detach a census by sending an explicit null', function () {
    Sanctum::actingAs(Resident::factory()->admin()->create());
    $groupbuy = Matter::factory()->create();
    $census = attachedCensus($groupbuy);

    $this->putJson('/api/admin/matters/'.$census->id, [
        'title' => $census->title,
        'related_matter_id' => null,
    ])->assertSuccessful();

    expect($census->refresh()->related_matter_id)->toBeNull();
});

test('the groupbuy detail carries its attached census with the register count', function () {
    $groupbuy = Matter::factory()->create();
    $census = attachedCensus($groupbuy);
    Stance::factory()->count(3)->censusAnswers()->for($census)->create();

    Sanctum::actingAs(Resident::factory()->create());

    $this->getJson('/api/matters/'.$groupbuy->id)
        ->assertSuccessful()
        ->assertJsonPath('data.attached_census.id', $census->id)
        ->assertJsonPath('data.attached_census.title', '中央空调需求摸底')
        ->assertJsonPath('data.attached_census.state', 'open')
        ->assertJsonPath('data.attached_census.register_count', 3);
});

test('an unapproved census stays off the groupbuy detail', function () {
    $groupbuy = Matter::factory()->create();
    attachedCensus($groupbuy, ['review_status' => MatterReviewStatus::Pending]);

    Sanctum::actingAs(Resident::factory()->create());

    $this->getJson('/api/matters/'.$groupbuy->id)
        ->assertSuccessful()
        ->assertJsonPath('data.attached_census', null);
});

test('the census exposes a back-link to the groupbuy it serves', function () {
    $groupbuy = Matter::factory()->create(['title' => '中央空调团购']);
    $census = attachedCensus($groupbuy);

    Sanctum::actingAs(Resident::factory()->create());

    $this->getJson('/api/matters/'.$census->id.'/census')
        ->assertSuccessful()
        ->assertJsonPath('related_matter.id', $groupbuy->id)
        ->assertJsonPath('related_matter.title', '中央空调团购');
});

test('a standalone census has no back-link', function () {
    $census = attachedCensus(Matter::factory()->create(), ['related_matter_id' => null]);

    Sanctum::actingAs(Resident::factory()->create());

    $this->getJson('/api/matters/'.$census->id.'/census')
        ->assertSuccessful()
        ->assertJsonPath('related_matter', null);
});
