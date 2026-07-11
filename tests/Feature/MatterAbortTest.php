<?php

use App\Matters\RightsType;
use App\Models\Matter;
use App\Models\Resident;
use App\Models\Stance;
use Laravel\Sanctum\Sanctum;

// ---- 旁路终态：失败/取消的收场出口，从任意非终态可直接进入 ----

test('the initiator can abort a groupbuy from any non-final state', function (string $factoryState) {
    $initiator = Resident::factory()->create();
    $matter = Matter::factory()->{$factoryState}()->for($initiator, 'initiator')->create();
    Sanctum::actingAs($initiator);

    $this->putJson("/api/matters/{$matter->id}/state", ['state' => 'aborted'])->assertSuccessful();

    expect($matter->refresh()->state)->toBe('aborted');
})->with(['negotiating', 'open']);

test('aborting is a real closure: no join, no review, no contacts, label says so', function () {
    $initiator = Resident::factory()->create(['phone' => '13900000000']);
    $matter = Matter::factory()->open()->for($initiator, 'initiator')->create();
    $participant = Resident::factory()->create(['phone' => '13811112222']);
    Stance::factory()->for($matter, 'matter')->for($participant, 'resident')->create(['payload' => ['share_contact' => true]]);

    Sanctum::actingAs($initiator);
    $this->putJson("/api/matters/{$matter->id}/state", ['state' => 'aborted'])->assertSuccessful();

    // 收场不是成团：不触发评价与联系互通
    Sanctum::actingAs($participant);
    $this->getJson("/api/matters/{$matter->id}")
        ->assertJsonPath('data.state_label', '未成团')
        ->assertJsonPath('data.join_open', false)
        ->assertJsonPath('data.review_open', false)
        ->assertJsonPath('data.contacts_open', false)
        ->assertJsonPath('initiator_contact', null);

    $this->putJson("/api/matters/{$matter->id}/review", ['rating' => 5])->assertUnprocessable();

    Sanctum::actingAs(Resident::factory()->create());
    $this->postJson("/api/matters/{$matter->id}/join")->assertUnprocessable();
});

test('aborted is final: the initiator cannot revive it and cannot abort a done deal', function () {
    $initiator = Resident::factory()->create();
    $aborted = Matter::factory()->aborted()->for($initiator, 'initiator')->create();
    $done = Matter::factory()->done()->for($initiator, 'initiator')->create();
    Sanctum::actingAs($initiator);

    $this->putJson("/api/matters/{$aborted->id}/state", ['state' => 'open'])->assertUnprocessable();
    $this->putJson("/api/matters/{$done->id}/state", ['state' => 'aborted'])->assertUnprocessable();

    expect($aborted->refresh()->state)->toBe('aborted')
        ->and($done->refresh()->state)->toBe('done');
});

test('a cancelled activity does not open reviews while a finished one does', function () {
    $initiator = Resident::factory()->create();
    $matter = Matter::factory()->activity()->for($initiator, 'initiator')->create();
    $participant = Resident::factory()->create();
    Stance::factory()->for($matter, 'matter')->for($participant, 'resident')->create();

    Sanctum::actingAs($initiator);
    $this->putJson("/api/matters/{$matter->id}/state", ['state' => 'aborted'])->assertSuccessful();

    Sanctum::actingAs($participant);
    $this->putJson("/api/matters/{$matter->id}/review", ['rating' => 5])->assertUnprocessable();

    $this->getJson("/api/matters/{$matter->id}")->assertJsonPath('data.state_label', '已取消');
});

test('admin-managed types have no abort escape hatch', function () {
    Sanctum::actingAs(Resident::factory()->admin()->create());

    $notice = Matter::factory()->notice()->create();
    $this->putJson("/api/admin/matters/{$notice->id}", [
        'title' => $notice->title,
        'state' => 'aborted',
    ])->assertUnprocessable();
});

test('admins can rescue an aborted matter back onto the track', function () {
    $matter = Matter::factory()->aborted()->create();
    Sanctum::actingAs(Resident::factory()->admin()->create());

    $this->putJson("/api/admin/matters/{$matter->id}", [
        'title' => $matter->title,
        'category' => $matter->category,
        'target_count' => $matter->target_count,
        'state' => 'open',
    ])->assertSuccessful();

    expect($matter->refresh()->state)->toBe('open');
});

test('aborted matters sink to the bottom of the feed', function () {
    Matter::factory()->aborted()->create(['title' => '没成的团']);
    Matter::factory()->open()->create(['title' => '接龙中的团']);

    Sanctum::actingAs(Resident::factory()->create());
    $titles = $this->getJson('/api/matters')->json('data.*.title');

    expect($titles)->toBe(['接龙中的团', '没成的团']);
});

test('an aborted rights action weighs like a resolved one in the feed', function () {
    $type = new RightsType;

    $aborted = Matter::factory()->rights()->create(['state' => 'aborted']);
    $collecting = Matter::factory()->rights()->create();

    expect($type->sortWeight($aborted))->toBe(9)
        ->and($type->sortWeight($collecting))->toBe(1);
});

// ---- 名单封存：接龙关闭后不能自行退出（成交名单/互通/评价资格都挂在上面） ----

test('participants cannot cancel their join once the matter closed', function (string $factoryState) {
    $matter = Matter::factory()->{$factoryState}()->create();
    $participant = Resident::factory()->create();
    Stance::factory()->for($matter, 'matter')->for($participant, 'resident')->create();

    Sanctum::actingAs($participant);
    $this->deleteJson("/api/matters/{$matter->id}/join")->assertUnprocessable();

    expect(Stance::where('mode', Stance::MODE_JOIN)->count())->toBe(1);
})->with(['done', 'aborted']);

test('participants can still cancel while the roster is open', function () {
    $matter = Matter::factory()->open()->create();
    $participant = Resident::factory()->create();
    Stance::factory()->for($matter, 'matter')->for($participant, 'resident')->create();

    Sanctum::actingAs($participant);
    $this->deleteJson("/api/matters/{$matter->id}/join")->assertSuccessful();

    expect(Stance::where('mode', Stance::MODE_JOIN)->count())->toBe(0);
});
