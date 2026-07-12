<?php

use App\Events\MatterApproved;
use App\Events\MatterStateChanged;
use App\Events\MatterUpdatePosted;
use App\Models\Matter;
use App\Models\Resident;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

test('approving a matter fires the approved event once', function () {
    Event::fake([MatterApproved::class]);
    $matter = Matter::factory()->pending()->create();
    Sanctum::actingAs(Resident::factory()->admin()->create());

    $this->putJson("/api/admin/matters/{$matter->id}/approve", ['is_approved' => true])->assertSuccessful();
    Event::assertDispatched(MatterApproved::class, fn (MatterApproved $event): bool => $event->matter->is($matter));

    // 重复审核已过审的事项不再触发
    $this->putJson("/api/admin/matters/{$matter->id}/approve", ['is_approved' => true])->assertSuccessful();
    Event::assertDispatchedTimes(MatterApproved::class, 1);
});

test('flipping the state fires the state changed event with the previous state', function () {
    Event::fake([MatterStateChanged::class]);
    $initiator = Resident::factory()->create();
    $matter = Matter::factory()->open()->for($initiator, 'initiator')->create();
    Sanctum::actingAs($initiator);

    $this->putJson("/api/matters/{$matter->id}/state", ['state' => 'done'])->assertSuccessful();

    Event::assertDispatched(
        MatterStateChanged::class,
        fn (MatterStateChanged $event): bool => $event->matter->is($matter) && $event->previousState === 'open',
    );

    // 流转到同一状态不算变化
    $this->putJson("/api/matters/{$matter->id}/state", ['state' => 'done'])->assertSuccessful();
    Event::assertDispatchedTimes(MatterStateChanged::class, 1);
});

test('posting a progress update fires the update posted event', function () {
    Event::fake([MatterUpdatePosted::class]);
    $initiator = Resident::factory()->create();
    $matter = Matter::factory()->open()->for($initiator, 'initiator')->create();
    Sanctum::actingAs($initiator);

    $this->postJson("/api/matters/{$matter->id}/updates", [
        'happened_on' => now()->toDateString(),
        'content' => '已和商家谈妥第一轮价格',
    ])->assertCreated();

    Event::assertDispatched(
        MatterUpdatePosted::class,
        fn (MatterUpdatePosted $event): bool => $event->update->matter_id === $matter->id,
    );
});
