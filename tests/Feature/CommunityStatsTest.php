<?php

use App\Models\Matter;
use App\Models\Party;
use App\Models\Resident;
use Laravel\Sanctum\Sanctum;

test('stats report the community overview', function () {
    Resident::factory()->count(3)->create();
    // 静默登录产生、还没选楼栋的路人不算「邻居」
    Resident::factory()->withoutUnit()->count(2)->create();
    Party::factory()->listed()->create();
    Sanctum::actingAs(Resident::factory()->create());

    $this->getJson('/api/stats')
        ->assertSuccessful()
        ->assertJsonPath('residents', 4)
        ->assertJsonPath('listed_parties', 1);
});

test('stats count only approved open censuses', function () {
    Matter::factory()->create(['type' => 'census', 'state' => 'open']);
    Matter::factory()->create(['type' => 'census', 'state' => 'closed']);
    Matter::factory()->pending()->create(['type' => 'census', 'state' => 'open']); // 未过审不算
    Sanctum::actingAs(Resident::factory()->create());

    $this->getJson('/api/stats')->assertJsonPath('open_census_count', 1);
});

test('guests cannot read stats', function () {
    $this->getJson('/api/stats')->assertUnauthorized();
});
