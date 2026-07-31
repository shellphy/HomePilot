<?php

use App\Ai\Agents\MatterExplainer;
use App\Models\Matter;
use App\Models\Party;
use App\Models\Resident;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;

function verificationCensus(): Matter
{
    return Matter::factory()->create([
        'type' => 'census',
        'state' => 'open',
        'payload' => [
            'modules' => [[
                'key' => 'basic',
                'title' => '基础',
                'questions' => [[
                    'key' => 'q1',
                    'text' => '是否参加？',
                    'type' => 'single',
                    'options' => ['参加', '不参加'],
                ]],
            ]],
        ],
    ]);
}

test('an admin reuses an invitation code until it expires', function () {
    $this->travelTo(now()->startOfDay()->addHours(10));
    $admin = Resident::factory()->admin()->create();
    Sanctum::actingAs($admin);
    Log::spy();

    $first = $this->postJson('/api/admin/invitation-code')
        ->assertSuccessful()
        ->json('data');
    $second = $this->postJson('/api/admin/invitation-code')
        ->assertSuccessful()
        ->json('data');

    expect(mb_strlen($first['code']))->toBe(8)
        ->and($second['code'])->toBe($first['code'])
        ->and($admin->refresh()->owner_invitation_code)->toBe($first['code'])
        ->and($admin->getRawOriginal('owner_invitation_code'))->toBe($first['code'])
        ->and($admin->owner_invitation_code_expires_at->equalTo(now()->addDay()->startOfDay()))->toBeTrue();

    Log::shouldHaveReceived('info')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $message === '审计 · 生成业主邀请码'
            && $context['admin_id'] === $admin->id);

    $this->travelTo(now()->addDay()->startOfDay()->addHours(9));

    $afterExpiry = $this->postJson('/api/admin/invitation-code')
        ->assertSuccessful()
        ->json('data');

    expect($afterExpiry['code'])->not->toBe($first['code']);
});

test('non admins cannot request invitation codes', function () {
    Sanctum::actingAs(Resident::factory()->create());

    $this->postJson('/api/admin/invitation-code')->assertForbidden();
});

test('an unverified owner can redeem a current invitation code case insensitively', function () {
    $admin = Resident::factory()->admin()->create();
    Sanctum::actingAs($admin);
    $code = $this->postJson('/api/admin/invitation-code')->json('data.code');

    $owner = Resident::factory()->unverifiedOwner()->create();
    Sanctum::actingAs($owner);
    Log::spy();

    $this->postJson('/api/me/verify-owner', ['invite_code' => mb_strtolower($code)])
        ->assertSuccessful()
        ->assertJsonPath('data.is_owner_verified', true)
        ->assertJsonPath('data.is_verified_participant', true);

    expect($owner->refresh()->owner_verified_at)->not->toBeNull()
        ->and($owner->owner_verified_by_id)->toBe($admin->id);

    Log::shouldHaveReceived('info')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $message === '审计 · 邀请码认证业主'
            && $context === ['resident_id' => $owner->id, 'admin_id' => $admin->id]);
});

test('expired and invalid invitation codes are rejected', function () {
    $this->travelTo(now()->startOfDay()->addHours(23)->addMinutes(58));
    $admin = Resident::factory()->admin()->create();
    Sanctum::actingAs($admin);
    $expiredCode = $this->postJson('/api/admin/invitation-code')->json('data.code');

    $this->travel(3)->minutes();
    $owner = Resident::factory()->unverifiedOwner()->create();
    Sanctum::actingAs($owner);
    Log::spy();

    $this->postJson('/api/me/verify-owner', ['invite_code' => $expiredCode])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('invite_code');
    $this->postJson('/api/me/verify-owner', ['invite_code' => 'ABCDEFGH'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('invite_code');

    expect($owner->refresh()->owner_verified_at)->toBeNull();

    Log::shouldHaveReceived('warning')
        ->twice()
        ->withArgs(fn (string $message, array $context): bool => $message === '无效业主邀请码'
            && $context['resident_id'] === $owner->id);
});

test('a related party must switch back to owner before redeeming an owner invitation', function () {
    $admin = Resident::factory()->admin()->create();
    Sanctum::actingAs($admin);
    $code = $this->postJson('/api/admin/invitation-code')->json('data.code');

    $merchant = Resident::factory()->merchant()->create();
    Sanctum::actingAs($merchant);
    Log::spy();

    $this->postJson('/api/me/verify-owner', ['invite_code' => $code])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('invite_code');

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $message === '相关方尝试业主邀请码认证'
            && $context['resident_id'] === $merchant->id);
});

test('unverified owners may browse but cannot interact or use AI', function () {
    MatterExplainer::fake()->preventStrayPrompts();

    $owner = Resident::factory()->unverifiedOwner()->create();
    $activity = Matter::factory()->activity()->create();
    $census = verificationCensus();
    Sanctum::actingAs($owner);
    Log::spy();

    $this->getJson("/api/matters/{$activity->id}")->assertSuccessful();
    $this->getJson('/api/me')
        ->assertSuccessful()
        ->assertJsonPath('data.has_unanswered_census', false);
    $this->getJson('/api/me/todos')
        ->assertSuccessful()
        ->assertJsonMissing(['type' => 'census_answer']);
    $this->postJson("/api/matters/{$activity->id}/join")
        ->assertForbidden()
        ->assertJsonPath('code', 'verification_required');
    $this->putJson("/api/matters/{$census->id}/census", ['answers' => ['q1' => '参加']])
        ->assertForbidden()
        ->assertJsonPath('code', 'verification_required');
    $this->postJson("/api/matters/{$activity->id}/ai-chat", ['question' => '介绍一下'])
        ->assertForbidden()
        ->assertJsonPath('code', 'verification_required');

    MatterExplainer::assertNeverPrompted();
    Log::shouldHaveReceived('warning')
        ->times(3)
        ->withArgs(fn (string $message, array $context): bool => $message === '未认证用户尝试受限操作'
            && $context['resident_id'] === $owner->id);
});

test('censuses accept every verified user and reject pending related parties', function () {
    $census = verificationCensus();

    $owner = Resident::factory()->create();
    Sanctum::actingAs($owner);
    $this->putJson("/api/matters/{$census->id}/census", ['answers' => ['q1' => '参加']])
        ->assertCreated();

    $approvedParty = Party::factory()->listed()->merchant()->create();
    $approvedMerchant = Resident::factory()->create(['affiliated_party_id' => $approvedParty->id]);
    Sanctum::actingAs($approvedMerchant);
    $this->putJson("/api/matters/{$census->id}/census", ['answers' => ['q1' => '参加']])
        ->assertCreated();

    $pendingMerchant = Resident::factory()->merchant()->create();
    Sanctum::actingAs($pendingMerchant);
    $this->putJson("/api/matters/{$census->id}/census", ['answers' => ['q1' => '参加']])
        ->assertForbidden()
        ->assertJsonPath('code', 'verification_required');
});

test('verified related parties without a building may answer contact censuses', function () {
    $census = verificationCensus();
    $census->update(['payload' => array_merge($census->payload, ['collects_contact' => true])]);
    $approvedParty = Party::factory()->listed()->merchant()->create();
    $merchant = Resident::factory()->withoutUnit()->create([
        'affiliated_party_id' => $approvedParty->id,
        'phone' => '13800138000',
    ]);
    Sanctum::actingAs($merchant);

    $this->putJson("/api/matters/{$census->id}/census", ['answers' => ['q1' => '参加']])
        ->assertCreated();
});
