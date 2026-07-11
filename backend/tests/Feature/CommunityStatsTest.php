<?php

use App\Matters\CensusType;
use App\Models\Matter;
use App\Models\Resident;
use App\Models\Stance;
use App\Settings\CommunitySettings;
use Laravel\Sanctum\Sanctum;

test('stats report the community overview', function () {
    Resident::factory()->count(3)->create();
    Sanctum::actingAs(Resident::factory()->create());

    $this->getJson('/api/stats')
        ->assertSuccessful()
        ->assertJsonPath('residents', 4)
        ->assertJsonPath('total_households', app(CommunitySettings::class)->total_households)
        ->assertJsonPath('category_interest', []);
});

test('stats aggregate category interest from census answers', function () {
    Sanctum::actingAs(Resident::factory()->create());

    $census = Matter::factory()->create(['type' => 'census', 'state' => 'open', 'is_approved' => true]);
    Stance::factory()->censusAnswers()->count(2)->for($census, 'matter')->create([
        'payload' => ['answers' => [CensusType::CATEGORY_INTEREST_KEY => ['门窗', '地暖']]],
    ]);
    Stance::factory()->censusAnswers()->for($census, 'matter')->create([
        'payload' => ['answers' => [CensusType::CATEGORY_INTEREST_KEY => ['门窗']]],
    ]);

    // 未审核征集的答案不计入
    $pending = Matter::factory()->create(['type' => 'census', 'state' => 'open', 'is_approved' => false]);
    Stance::factory()->censusAnswers()->for($pending, 'matter')->create([
        'payload' => ['answers' => [CensusType::CATEGORY_INTEREST_KEY => ['全屋定制']]],
    ]);

    $this->getJson('/api/stats')
        ->assertSuccessful()
        ->assertJsonPath('category_interest.门窗', 3)
        ->assertJsonPath('category_interest.地暖', 2)
        ->assertJsonMissingPath('category_interest.全屋定制');
});

test('guests cannot read stats', function () {
    $this->getJson('/api/stats')->assertUnauthorized();
});
