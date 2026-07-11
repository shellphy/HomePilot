<?php

use App\Models\Matter;
use App\Models\Party;
use App\Models\Record;
use App\Models\Resident;
use App\Settings\CommunitySettings;
use Laravel\Sanctum\Sanctum;

test('admin routes reject ordinary members', function (string $method, string $uri) {
    Sanctum::actingAs(Resident::factory()->create());

    $this->json($method, $uri)->assertForbidden();
})->with([
    ['GET', '/api/admin/matters'],
    ['POST', '/api/admin/matters'],
    ['GET', '/api/admin/parties'],
    ['GET', '/api/admin/settings'],
    ['PUT', '/api/admin/settings'],
]);

test('me exposes the admin flag', function () {
    Sanctum::actingAs(Resident::factory()->admin()->create());

    $this->getJson('/api/me')->assertJsonPath('data.is_admin', true);
});

test('the admin:grant command grants and revokes by id or wechat id', function () {
    $resident = Resident::factory()->create(['wechat_id' => 'laok_2026']);

    $this->artisan('admin:grant', ['resident' => 'laok_2026'])->assertSuccessful();
    expect($resident->refresh()->is_admin)->toBeTrue();

    $this->artisan('admin:grant', ['resident' => (string) $resident->id, '--revoke' => true])->assertSuccessful();
    expect($resident->refresh()->is_admin)->toBeFalse();
});

test('admin sees the pending queue and approves matters onto the feed', function () {
    $pending = Matter::factory()->create(['is_approved' => false, 'title' => '待审核的团']);
    Matter::factory()->open()->create();
    Sanctum::actingAs(Resident::factory()->admin()->create());

    $this->getJson('/api/admin/matters?pending=1')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', '待审核的团')
        ->assertJsonPath('pending_count', 1);

    $this->putJson("/api/admin/matters/{$pending->id}/approve", ['is_approved' => true])
        ->assertSuccessful()
        ->assertJsonPath('data.is_approved', true);

    expect($pending->refresh()->is_approved)->toBeTrue();
});

test('admin publishes a census with a questionnaire and missing keys are generated', function () {
    Sanctum::actingAs(Resident::factory()->admin()->create());

    $response = $this->postJson('/api/admin/matters', [
        'type' => 'census',
        'title' => '车位需求摸底',
        'state' => 'open',
        'payload' => [
            'pitch' => '统计有多少户需要车位',
            'collects_contact' => false,
            'modules' => [[
                'title' => '基础',
                'questions' => [
                    ['text' => '需要几个车位？', 'type' => 'single', 'options' => ['0 个', '1 个', '2 个']],
                ],
            ]],
        ],
    ])->assertCreated();

    $module = $response->json('data.payload.modules.0');
    expect($module['key'])->toStartWith('m_')
        ->and($module['questions'][0]['key'])->toStartWith('q_');

    // 发布即公示，业主端能答题
    $matterId = $response->json('data.id');
    Sanctum::actingAs(Resident::factory()->create());
    $this->putJson("/api/matters/{$matterId}/census", [
        'answers' => [$module['questions'][0]['key'] => '1 个'],
    ])->assertCreated();
});

test('admin edits keep existing question keys and unrelated payload fields', function () {
    $census = Matter::factory()->create([
        'type' => 'census',
        'state' => 'open',
        'payload' => [
            'pitch' => '原说明',
            'final_note' => '别的字段',
            'modules' => [[
                'key' => 'basic',
                'title' => '基础',
                'questions' => [['key' => 'q1', 'text' => '旧题', 'type' => 'single', 'options' => ['A', 'B']]],
            ]],
        ],
    ]);
    Sanctum::actingAs(Resident::factory()->admin()->create());

    $this->putJson("/api/admin/matters/{$census->id}", [
        'title' => $census->title,
        'payload' => [
            'modules' => [[
                'key' => 'basic',
                'title' => '基础',
                'questions' => [['key' => 'q1', 'text' => '改了题面', 'type' => 'single', 'options' => ['A', 'B', 'C']]],
            ]],
        ],
    ])->assertSuccessful()
        ->assertJsonPath('data.payload.modules.0.questions.0.key', 'q1')
        ->assertJsonPath('data.payload.final_note', '别的字段');
});

test('census questionnaires must have valid structure', function () {
    Sanctum::actingAs(Resident::factory()->admin()->create());

    $this->postJson('/api/admin/matters', [
        'type' => 'census',
        'title' => '坏问卷',
        'payload' => [
            'modules' => [[
                'title' => '基础',
                'questions' => [['text' => '只有一个选项', 'type' => 'single', 'options' => ['唯一']]],
            ]],
        ],
    ])->assertUnprocessable();
});

test('admin reads census records with contact details and resolved question text', function () {
    $census = Matter::factory()->create([
        'type' => 'census',
        'state' => 'open',
        'payload' => [
            'modules' => [[
                'key' => 'basic',
                'title' => '基础',
                'questions' => [['key' => 'q1', 'text' => '打算怎么装？', 'type' => 'single', 'options' => ['清包', '半包']]],
            ]],
        ],
    ]);
    $resident = Resident::factory()->inUnit('3栋')->create(['nickname' => '老K', 'wechat_id' => 'laok', 'room_label' => '1802']);
    Record::factory()->create([
        'matter_id' => $census->id,
        'resident_id' => $resident->id,
        'mode' => Record::MODE_REGISTER,
        'payload' => ['answers' => ['q1' => '半包']],
    ]);
    Sanctum::actingAs(Resident::factory()->admin()->create());

    $this->getJson("/api/admin/matters/{$census->id}/records")
        ->assertSuccessful()
        ->assertJsonPath('data.0.unit_label', '3栋')
        ->assertJsonPath('data.0.room_label', '1802')
        ->assertJsonPath('data.0.wechat_id', 'laok')
        ->assertJsonPath('data.0.answers.0.question', '打算怎么装？')
        ->assertJsonPath('data.0.answers.0.answer', '半包');
});

test('admin certifies parties onto the public list', function () {
    $party = Party::factory()->merchant()->create();
    Sanctum::actingAs(Resident::factory()->admin()->create());

    $this->getJson('/api/admin/parties')
        ->assertSuccessful()
        ->assertJsonPath('data.0.is_listed', false);

    $this->putJson("/api/admin/parties/{$party->id}", ['is_listed' => true])
        ->assertSuccessful();

    expect($party->refresh()->is_listed)->toBeTrue();
});

test('admin reads and updates community settings which flow through to options', function () {
    Sanctum::actingAs(Resident::factory()->admin()->create());

    $response = $this->getJson('/api/admin/settings')->assertSuccessful();

    // 表单结构随设置值一起下发，管理端据此渲染，字段清单不在前端维护
    $groups = $response->json('groups');
    expect($groups)->not->toBeEmpty();
    $fieldKeys = collect($groups)->flatMap(fn (array $group) => collect($group['fields'])->pluck('key'));
    expect($fieldKeys->sort()->values()->all())
        ->toBe(collect($response->json('data'))->keys()->sort()->values()->all());

    $settings = $response->json('data');
    $settings['slogan'] = '新口号';
    $settings['categories'] = ['门窗', '地暖'];

    $this->putJson('/api/admin/settings', $settings)->assertSuccessful();

    expect(app(CommunitySettings::class)->refresh()->slogan)->toBe('新口号');
    $this->getJson('/api/options')
        ->assertJsonPath('community.slogan', '新口号')
        ->assertJsonPath('categories', ['门窗', '地暖']);
});

test('admin deletes a matter', function () {
    $matter = Matter::factory()->create();
    Sanctum::actingAs(Resident::factory()->admin()->create());

    $this->deleteJson("/api/admin/matters/{$matter->id}")->assertSuccessful();

    expect(Matter::find($matter->id))->toBeNull();
});
