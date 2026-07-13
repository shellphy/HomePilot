<?php

use App\Models\Matter;
use App\Models\MatterQuestion;
use App\Models\Party;
use App\Models\Resident;
use App\Models\Stance;
use Laravel\Sanctum\Sanctum;

test('a fresh resident has an empty todo list', function () {
    Sanctum::actingAs(Resident::factory()->create(['unit_label' => '3栋']));

    $this->getJson('/api/me/todos')->assertSuccessful()->assertJsonCount(0, 'data');
});

test('an unanswered open census shows up, and disappears once answered', function () {
    $resident = Resident::factory()->create(['unit_label' => '3栋']);
    $census = Matter::factory()->create(['type' => 'census', 'state' => 'open', 'title' => '装修意向摸底']);
    Sanctum::actingAs($resident);

    $this->getJson('/api/me/todos')
        ->assertJsonPath('data.0.type', 'census_answer')
        ->assertJsonPath('data.0.title', '装修意向摸底')
        ->assertJsonPath('data.0.action', '去回答')
        ->assertJsonPath('data.0.matter_id', $census->id);

    Stance::factory()->censusAnswers()->for($census, 'matter')->for($resident, 'resident')->create();

    $this->getJson('/api/me/todos')->assertJsonMissing(['type' => 'census_answer']);
});

test('a groupbuy where I only registered intent asks me to confirm, once open', function () {
    $resident = Resident::factory()->create(['unit_label' => '3栋']);
    $groupbuy = Matter::factory()->open()->create();
    Stance::factory()->intent()->for($groupbuy, 'matter')->for($resident, 'resident')->create();
    Sanctum::actingAs($resident);

    $this->getJson('/api/me/todos')
        ->assertJsonPath('data.0.type', 'groupbuy_confirm')
        ->assertJsonPath('data.0.action', '确认参团');
});

test('a done groupbuy I joined and have not reviewed asks me to review', function () {
    $resident = Resident::factory()->create(['unit_label' => '3栋']);
    $groupbuy = Matter::factory()->done()->create();
    Stance::factory()->for($groupbuy, 'matter')->for($resident, 'resident')->create(); // confirmed join
    Sanctum::actingAs($resident);

    $this->getJson('/api/me/todos')->assertJsonPath('data.0.type', 'review');

    Stance::factory()->review()->for($groupbuy, 'matter')->for($resident, 'resident')->create();
    $this->getJson('/api/me/todos')->assertJsonMissing(['type' => 'review']);
});

test('my matter with unanswered questions tells me how many to answer', function () {
    $resident = Resident::factory()->create(['unit_label' => '3栋']);
    $matter = Matter::factory()->for($resident, 'initiator')->create();
    MatterQuestion::factory()->count(2)->for($matter)->create();
    Sanctum::actingAs($resident);

    $this->getJson('/api/me/todos')
        ->assertJsonPath('data.0.type', 'answer_question')
        ->assertJsonPath('data.0.action', '回答 2 个提问');
});

test('my done groupbuy without a posted deal asks me to post it', function () {
    $resident = Resident::factory()->create(['unit_label' => '3栋']);
    Matter::factory()->done()->for($resident, 'initiator')->create();
    Sanctum::actingAs($resident);

    $this->getJson('/api/me/todos')->assertJsonPath('data.0.type', 'post_deal');
});

test('an action todo hides the plain progress todo for the same matter', function () {
    $resident = Resident::factory()->create(['unit_label' => '3栋']);
    $other = Resident::factory()->create();
    $groupbuy = Matter::factory()->open()->create([
        'last_activity_at' => now(),
        'last_activity_resident_id' => $other->id,
    ]);
    Stance::factory()->intent()->for($groupbuy, 'matter')->for($resident, 'resident')->create();
    Sanctum::actingAs($resident);

    $response = $this->getJson('/api/me/todos')->assertSuccessful();

    $forMatter = collect($response->json('data'))->where('matter_id', $groupbuy->id);
    expect($forMatter)->toHaveCount(1)
        ->and($forMatter->first()['type'])->toBe('groupbuy_confirm');
});

test('admin queues show only for admins', function () {
    Matter::factory()->create(['review_status' => 'pending']);
    Party::factory()->create(); // pending party

    Sanctum::actingAs(Resident::factory()->create(['unit_label' => '3栋']));
    $this->getJson('/api/me/todos')
        ->assertJsonMissing(['type' => 'admin_review'])
        ->assertJsonMissing(['type' => 'admin_party']);

    Sanctum::actingAs(Resident::factory()->admin()->create(['unit_label' => '1栋']));
    $this->getJson('/api/me/todos')
        ->assertJsonFragment(['type' => 'admin_review', 'action' => '1 件待审核'])
        ->assertJsonFragment(['type' => 'admin_party', 'action' => '1 家待核验']);
});

test('todos with a deadline sort ahead of those without', function () {
    $resident = Resident::factory()->create(['unit_label' => '3栋']);
    // 无截止的行动待办：我牵头、有未答问题
    $noDeadline = Matter::factory()->for($resident, 'initiator')->create();
    MatterQuestion::factory()->for($noDeadline)->create();
    // 有截止的：待确认参团的团购
    $withDeadline = Matter::factory()->open()->create(['registration_deadline_at' => now()->addDays(2)]);
    Stance::factory()->intent()->for($withDeadline, 'matter')->for($resident, 'resident')->create();
    Sanctum::actingAs($resident);

    $this->getJson('/api/me/todos')
        ->assertJsonPath('data.0.matter_id', $withDeadline->id)
        ->assertJsonPath('data.0.type', 'groupbuy_confirm');
});
