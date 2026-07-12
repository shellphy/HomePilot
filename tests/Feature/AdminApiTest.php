<?php

use App\Models\Matter;
use App\Models\Party;
use App\Models\Resident;
use App\Models\Stance;
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

test('the admin:grant command grants and revokes by id or phone', function () {
    $resident = Resident::factory()->create(['phone' => '13800138000']);

    $this->artisan('admin:grant', ['resident' => '13800138000'])->assertSuccessful();
    expect($resident->refresh()->is_admin)->toBeTrue();

    $this->artisan('admin:grant', ['resident' => (string) $resident->id, '--revoke' => true])->assertSuccessful();
    expect($resident->refresh()->is_admin)->toBeFalse();
});

test('admin sees the pending queue and approves matters onto the feed', function () {
    $pending = Matter::factory()->pending()->create(['title' => '待审核的团']);
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
    $resident = Resident::factory()->inUnit('3栋')->create(['nickname' => '老K', 'phone' => '13800138000', 'room_label' => '1802']);
    Stance::factory()->create([
        'matter_id' => $census->id,
        'resident_id' => $resident->id,
        'mode' => Stance::MODE_REGISTER,
        'payload' => ['answers' => ['q1' => '半包']],
    ]);
    Sanctum::actingAs(Resident::factory()->admin()->create());

    $this->getJson("/api/admin/matters/{$census->id}/registrations")
        ->assertSuccessful()
        ->assertJsonPath('data.0.unit_label', '3栋')
        ->assertJsonPath('data.0.room_label', '1802')
        ->assertJsonPath('data.0.phone', '13800138000')
        ->assertJsonPath('data.0.answers.0.question', '打算怎么装？')
        ->assertJsonPath('data.0.answers.0.answer', '半包');
});

test('admin certifies parties onto the public list', function () {
    $party = Party::factory()->merchant()->create();
    $owner = Resident::factory()->create(['affiliated_party_id' => $party->id]);
    Sanctum::actingAs(Resident::factory()->admin()->create());

    // 认证前要能联系上入驻方核实，列表带归属人的授权手机号
    $this->getJson('/api/admin/parties')
        ->assertSuccessful()
        ->assertJsonPath('data.0.is_listed', false)
        ->assertJsonPath('data.0.phone', $owner->phone);

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
    $settings['buildings'] = ['1栋', '2栋'];

    $this->putJson('/api/admin/settings', $settings)->assertSuccessful();

    expect(app(CommunitySettings::class)->refresh()->slogan)->toBe('新口号');
    $this->getJson('/api/options')
        ->assertJsonPath('community.slogan', '新口号')
        ->assertJsonPath('buildings', ['1栋', '2栋']);
});

test('admin deletes a matter', function () {
    $matter = Matter::factory()->create();
    Sanctum::actingAs(Resident::factory()->admin()->create());

    $this->deleteJson("/api/admin/matters/{$matter->id}")->assertSuccessful();

    // 软删除：对外消失，但库里可恢复
    expect(Matter::find($matter->id))->toBeNull()
        ->and(Matter::withTrashed()->find($matter->id))->not->toBeNull();
});

test('census schema accepts text questions without options and keeps question notes', function () {
    Sanctum::actingAs(Resident::factory()->admin()->create());

    $this->postJson('/api/admin/matters', [
        'type' => 'census',
        'title' => '装修探店摸底',
        'payload' => [
            'modules' => [[
                'title' => '探店情况',
                'questions' => [
                    ['text' => '去看过哪些装修公司？', 'type' => 'multi', 'options' => ['A 公司', 'B 公司'], 'note' => '去过门店或约过量房都算'],
                    ['text' => '踩过什么坑？', 'type' => 'text'],
                ],
            ]],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('data.payload.modules.0.questions.0.note', '去过门店或约过量房都算')
        ->assertJsonPath('data.payload.modules.0.questions.1.type', 'text');

    // 选择题仍必须带至少两个选项
    $this->postJson('/api/admin/matters', [
        'type' => 'census',
        'title' => '坏问卷',
        'payload' => [
            'modules' => [[
                'title' => '模块',
                'questions' => [['text' => '单选没给选项', 'type' => 'single']],
            ]],
        ],
    ])->assertUnprocessable();
});

test('the admin queue signs matters with the initiator party snapshot', function () {
    $merchant = Resident::factory()->merchant('青城中央空调')->create();
    $merchant->affiliatedParty->update(['is_listed' => true]);
    Matter::factory()->pending()->create([
        'initiator_id' => $merchant->id,
        'initiator_party_id' => $merchant->affiliated_party_id,
    ]);

    Sanctum::actingAs(Resident::factory()->admin()->create());
    $this->getJson('/api/admin/matters?pending=1')
        ->assertSuccessful()
        ->assertJsonPath('data.0.initiator', '商家 · 青城中央空调（已认证）');
});

test('admin publishing a groupbuy requires the same category and target count as the member form', function () {
    Sanctum::actingAs(Resident::factory()->admin()->create());

    // 两条创建路径校验强度一致：管理员代发的团购同样必须有品类与目标人数
    $this->postJson('/api/admin/matters', [
        'type' => 'groupbuy',
        'title' => '中央空调团购',
    ])->assertUnprocessable()->assertJsonValidationErrors(['category', 'target_count']);

    // 团购没有「不设目标」：目标人数是去谈价的筹码，管理端也至少 1 人
    $this->postJson('/api/admin/matters', [
        'type' => 'groupbuy',
        'title' => '中央空调团购',
        'category' => '中央空调',
        'target_count' => 0,
    ])->assertUnprocessable()->assertJsonValidationErrors(['target_count']);

    $this->postJson('/api/admin/matters', [
        'type' => 'groupbuy',
        'title' => '中央空调团购',
        'category' => '中央空调',
        'target_count' => 30,
    ])->assertCreated();
});

test('admin payload fields share the member form field limits', function () {
    Sanctum::actingAs(Resident::factory()->admin()->create());

    // payload 规则直接复用业主端 payloadRules：上限一致，不再各改各的漂移开
    $this->postJson('/api/admin/matters', [
        'type' => 'groupbuy',
        'title' => '门窗团购',
        'category' => '门窗',
        'target_count' => 20,
        'payload' => ['perk' => str_repeat('优', 150)],
    ])->assertUnprocessable()->assertJsonValidationErrors(['payload.perk']);

    $this->postJson('/api/admin/matters', [
        'type' => 'groupbuy',
        'title' => '门窗团购',
        'category' => '门窗',
        'target_count' => 20,
        'payload' => ['glossary' => [['term' => '断桥铝', 'explain' => str_repeat('解', 400)]]],
    ])->assertUnprocessable()->assertJsonValidationErrors(['payload.glossary.0.explain']);
});

test('admin notices require a body', function () {
    Sanctum::actingAs(Resident::factory()->admin()->create());

    // 公告的 body 规则同样来自类型定义（required）：没正文的公告业主端没法看
    $this->postJson('/api/admin/matters', [
        'type' => 'notice',
        'title' => '停水通知',
    ])->assertUnprocessable()->assertJsonValidationErrors(['payload.body']);

    $this->postJson('/api/admin/matters', [
        'type' => 'notice',
        'title' => '停水通知',
        'payload' => ['body' => '周三上午 9 点到 12 点全小区停水，请提前储水。'],
    ])->assertCreated();
});
