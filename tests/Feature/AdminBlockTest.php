<?php

use App\Models\Matter;
use App\Models\Resident;
use Laravel\Sanctum\Sanctum;

test('an admin blocks a resident, who then shows up in the block list', function () {
    $admin = Resident::factory()->admin()->create(['nickname' => '张工', 'unit_label' => '1栋']);
    $troll = Resident::factory()->create(['nickname' => '老赖', 'unit_label' => '3栋']);
    Sanctum::actingAs($admin);

    $this->postJson('/api/admin/blocks', ['resident_id' => $troll->id])
        ->assertCreated()
        ->assertJsonPath('data.name', '3栋 老赖')
        ->assertJsonPath('data.blocked_by', '1栋 张工');

    expect($troll->refresh()->isBlocked())->toBeTrue();

    $this->getJson('/api/admin/blocks')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', '3栋 老赖');
});

test('blocking rejects an unknown id, an admin, or an already blocked member', function () {
    Sanctum::actingAs(Resident::factory()->admin()->create());

    $this->postJson('/api/admin/blocks', ['resident_id' => 999999])
        ->assertStatus(422)->assertJsonValidationErrors('resident_id');

    $otherAdmin = Resident::factory()->admin()->create();
    $this->postJson('/api/admin/blocks', ['resident_id' => $otherAdmin->id])
        ->assertStatus(422)->assertJsonValidationErrors('resident_id');
    expect($otherAdmin->refresh()->isBlocked())->toBeFalse();

    $blocked = Resident::factory()->blocked()->create();
    $this->postJson('/api/admin/blocks', ['resident_id' => $blocked->id])
        ->assertStatus(422)->assertJsonValidationErrors('resident_id');
});

test('an admin unblocks a resident', function () {
    Sanctum::actingAs(Resident::factory()->admin()->create());
    $blocked = Resident::factory()->blocked()->create();

    $this->deleteJson("/api/admin/blocks/{$blocked->id}")->assertSuccessful();
    expect($blocked->refresh()->isBlocked())->toBeFalse();
});

test('a blocked resident cannot join or initiate', function () {
    $matter = Matter::factory()->rights()->create();
    Sanctum::actingAs(Resident::factory()->blocked()->create(['unit_label' => '3栋']));

    $this->postJson("/api/matters/{$matter->id}/join")->assertForbidden();
    $this->postJson('/api/matters', ['type' => 'activity', 'title' => '想张罗个活动'])->assertForbidden();
});

test('a blocked resident cannot use interaction endpoints but can still browse and log out', function () {
    $matter = Matter::factory()->create();
    $blocked = Resident::factory()->blocked()->create();
    Sanctum::actingAs($blocked);

    $this->getJson('/api/matters')->assertSuccessful();
    $this->getJson("/api/matters/{$matter->id}")->assertSuccessful();

    $this->postJson("/api/matters/{$matter->id}/questions", ['content' => '还能提问吗'])->assertForbidden();
    $this->putJson("/api/matters/{$matter->id}/review", ['rating' => 5])->assertForbidden();
    $this->postJson("/api/matters/{$matter->id}/updates", ['content' => '更新'])->assertForbidden();
    $this->postJson('/api/glossary/draft', ['term' => '术语', 'draft' => '说明'])->assertForbidden();
    $this->postJson("/api/matters/{$matter->id}/ai-chat", ['question' => '问题'])->assertForbidden();
    $this->postJson('/api/uploads')->assertForbidden();

    $this->postJson('/api/logout')->assertSuccessful();
});

test('a non-admin cannot reach block management', function (string $method, string $uri) {
    Sanctum::actingAs(Resident::factory()->create());

    $this->json($method, $uri)->assertForbidden();
})->with([
    ['GET', '/api/admin/blocks'],
    ['POST', '/api/admin/blocks'],
    ['DELETE', '/api/admin/blocks/1'],
]);
