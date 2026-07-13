<?php

use App\Events\GroupbuyTermsRevised;
use App\Models\Matter;
use App\Models\Party;
use App\Models\Resident;
use App\Models\Stance;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

test('an owner-initiated groupbuy must disclose the merchant relationship', function () {
    Sanctum::actingAs(Resident::factory()->create(['unit_label' => '3栋']));
    $base = ['type' => 'groupbuy', 'title' => '门窗团', 'category' => '门窗', 'target_count' => 10];

    $this->postJson('/api/matters', $base)
        ->assertStatus(422)->assertJsonValidationErrors('relationship');

    // 有返点必须说明去向
    $this->postJson('/api/matters', [...$base, 'relationship' => 'rebate'])
        ->assertStatus(422)->assertJsonValidationErrors('rebate_note');

    $this->postJson('/api/matters', [...$base, 'relationship' => 'none'])->assertCreated();
});

test('an owner cannot claim 商家直供', function () {
    Sanctum::actingAs(Resident::factory()->create(['unit_label' => '3栋']));

    $this->postJson('/api/matters', [
        'type' => 'groupbuy', 'title' => '门窗团', 'category' => '门窗', 'target_count' => 10,
        'relationship' => 'merchant_direct',
    ])->assertStatus(422)->assertJsonValidationErrors('relationship');
});

test('a merchant-initiated groupbuy is labelled 商家直供 regardless of input', function () {
    $merchant = Party::factory()->listed()->create();
    Sanctum::actingAs(Resident::factory()->create(['affiliated_party_id' => $merchant->id]));

    $res = $this->postJson('/api/matters', [
        'type' => 'groupbuy', 'title' => '直供团', 'category' => '空调', 'target_count' => 5,
        'relationship' => 'none',
    ])->assertCreated();

    expect(Matter::find($res->json('data.id'))->payloadValue('relationship'))->toBe('merchant_direct');
});

test('changing groupbuy terms downgrades confirmed participants and notifies them', function () {
    Event::fake([GroupbuyTermsRevised::class]);
    $initiator = Resident::factory()->create(['unit_label' => '3栋']);
    $matter = Matter::factory()->open()->for($initiator, 'initiator')->create([
        'payload' => ['relationship' => 'none', 'terms' => [['label' => '定金', 'value' => '500']], 'needs_survey' => false],
    ]);
    $joiner = Resident::factory()->create();
    Stance::factory()->for($matter, 'matter')->for($joiner, 'resident')->create(); // 确认参团

    Sanctum::actingAs($initiator);
    $this->putJson("/api/matters/{$matter->id}", [
        'title' => $matter->title,
        'category' => $matter->category,
        'target_count' => $matter->target_count,
        'relationship' => 'none',
        'terms' => [['label' => '定金', 'value' => '1000']], // 实质变更
    ])->assertSuccessful();

    expect($matter->joins()->where('resident_id', $joiner->id)->first()->joinStageValue())
        ->toBe(Stance::JOIN_STAGE_INTENT);
    Event::assertDispatched(GroupbuyTermsRevised::class);
});

test('changing only the pitch keeps confirmations intact', function () {
    Event::fake([GroupbuyTermsRevised::class]);
    $initiator = Resident::factory()->create(['unit_label' => '3栋']);
    $matter = Matter::factory()->open()->for($initiator, 'initiator')->create([
        'payload' => ['relationship' => 'none', 'terms' => [['label' => '定金', 'value' => '500']], 'pitch' => '老卖点', 'needs_survey' => false],
    ]);
    $joiner = Resident::factory()->create();
    Stance::factory()->for($matter, 'matter')->for($joiner, 'resident')->create();

    Sanctum::actingAs($initiator);
    $this->putJson("/api/matters/{$matter->id}", [
        'title' => $matter->title,
        'category' => $matter->category,
        'target_count' => $matter->target_count,
        'relationship' => 'none',
        'terms' => [['label' => '定金', 'value' => '500']], // 不变
        'pitch' => '新卖点', // 非实质
    ])->assertSuccessful();

    expect($matter->joins()->where('resident_id', $joiner->id)->first()->joinStageValue())
        ->toBe(Stance::JOIN_STAGE_CONFIRMED);
    Event::assertNotDispatched(GroupbuyTermsRevised::class);
});
