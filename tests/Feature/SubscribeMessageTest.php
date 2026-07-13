<?php

use App\Models\Matter;
use App\Models\Party;
use App\Models\Resident;
use App\Models\Stance;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config([
        'services.wechat.appid' => 'test-appid',
        'services.wechat.secret' => 'test-secret',
        'services.wechat.subscribe_template_id' => 'tpl-test',
    ]);

    Http::fake([
        'api.weixin.qq.com/cgi-bin/stable_token' => Http::response(['access_token' => 'fake-token']),
        'api.weixin.qq.com/cgi-bin/message/subscribe/send*' => Http::response(['errcode' => 0, 'errmsg' => 'ok']),
    ]);
});

/**
 * 发出去的订阅消息请求体（不含换 token 的请求）。
 *
 * @return Collection<int, array<string, mixed>>
 */
function sentSubscribeMessages(): Collection
{
    return Http::recorded(fn (Request $request): bool => str_contains($request->url(), 'subscribe/send'))
        ->map(fn (array $pair): array => $pair[0]->data());
}

test('flipping the state notifies participants with template fields but skips the actor', function () {
    $initiator = Resident::factory()->create();
    $matter = Matter::factory()->open()->for($initiator, 'initiator')->create(['title' => '中央空调团购']);
    $participant = Resident::factory()->create();
    Stance::factory()->for($matter, 'matter')->for($participant, 'resident')->create();

    Sanctum::actingAs($initiator);
    $this->putJson("/api/matters/{$matter->id}/state", ['state' => 'done'])->assertSuccessful();

    $messages = sentSubscribeMessages();
    expect($messages)->toHaveCount(1);

    $message = $messages->sole();
    expect($message['touser'])->toBe($participant->openid)
        ->and($message['template_id'])->toBe('tpl-test')
        ->and($message['page'])->toBe("pages/matter/index?id={$matter->id}")
        ->and($message['data']['thing1']['value'])->toBe('中央空调团购')
        ->and($message['data']['thing5']['value'])->toBe('团购')
        ->and($message['data']['short_thing3']['value'])->toBe('已成团');
});

test('approving notifies the initiator and rejecting carries the reason', function () {
    $initiator = Resident::factory()->create();
    $matter = Matter::factory()->pending()->for($initiator, 'initiator')->create();
    Sanctum::actingAs(Resident::factory()->admin()->create());

    $this->putJson("/api/admin/matters/{$matter->id}/approve", ['is_approved' => true])->assertSuccessful();

    $approved = sentSubscribeMessages()->sole();
    expect($approved['touser'])->toBe($initiator->openid)
        ->and($approved['data']['short_thing3']['value'])->toBe('已公示');

    $this->putJson("/api/admin/matters/{$matter->id}/approve", ['is_approved' => false, 'reason' => '标题写得太模糊'])
        ->assertSuccessful();

    $rejected = sentSubscribeMessages()->last();
    expect($rejected['touser'])->toBe($initiator->openid)
        ->and($rejected['data']['short_thing3']['value'])->toBe('未过审')
        ->and($rejected['data']['thing4']['value'])->toBe('标题写得太模糊');
});

test('joining notifies the initiator with a type-appropriate word', function () {
    $initiator = Resident::factory()->create();
    $activity = Matter::factory()->activity()->for($initiator, 'initiator')->create();

    Sanctum::actingAs(Resident::factory()->inUnit('5栋')->create(['nickname' => '老王']));
    $this->postJson("/api/matters/{$activity->id}/join")->assertCreated();

    $message = sentSubscribeMessages()->sole();
    expect($message['touser'])->toBe($initiator->openid)
        ->and($message['data']['short_thing3']['value'])->toBe('新增报名')
        ->and($message['data']['thing4']['value'])->toBe('5栋 老王 加入了名单');

    // 互助的事件词不一样：响应比报名更贴场景
    $aid = Matter::factory()->aid()->for($initiator, 'initiator')->create();
    $this->postJson("/api/matters/{$aid->id}/join")->assertCreated();

    expect(sentSubscribeMessages()->last()['data']['short_thing3']['value'])->toBe('有人响应');
});

test('upgrading an intent to a confirmed join notifies the initiator', function () {
    $initiator = Resident::factory()->create();
    $matter = Matter::factory()->open()->for($initiator, 'initiator')->create();
    $participant = Resident::factory()->create();
    Stance::factory()->intent()->for($matter, 'matter')->for($participant, 'resident')->create();

    Sanctum::actingAs($participant);
    $this->postJson("/api/matters/{$matter->id}/join")->assertCreated();

    expect(sentSubscribeMessages()->sole()['data']['short_thing3']['value'])->toBe('确认参团');
});

test('a new review notifies the initiator once and revisions stay quiet', function () {
    $initiator = Resident::factory()->create();
    $matter = Matter::factory()->done()->for($initiator, 'initiator')->create();
    $participant = Resident::factory()->create();
    Stance::factory()->for($matter, 'matter')->for($participant, 'resident')->create();

    Sanctum::actingAs($participant);
    $this->putJson("/api/matters/{$matter->id}/review", ['rating' => 5, 'content' => '靠谱'])->assertCreated();

    $message = sentSubscribeMessages()->sole();
    expect($message['touser'])->toBe($initiator->openid)
        ->and($message['data']['short_thing3']['value'])->toBe('收到评价')
        ->and($message['data']['thing4']['value'])->toBe('收到一条 5 星评价');

    // 修改已有评价不再通知
    $this->putJson("/api/matters/{$matter->id}/review", ['rating' => 4])->assertSuccessful();
    expect(sentSubscribeMessages())->toHaveCount(1);
});

test('publishing the deal notifies confirmed participants only', function () {
    $initiator = Resident::factory()->create();
    $matter = Matter::factory()->done()->for($initiator, 'initiator')->create();
    $confirmed = Resident::factory()->create();
    $intent = Resident::factory()->create();
    Stance::factory()->for($matter, 'matter')->for($confirmed, 'resident')->create();
    Stance::factory()->intent()->for($matter, 'matter')->for($intent, 'resident')->create();

    Sanctum::actingAs($initiator);
    $this->putJson("/api/matters/{$matter->id}/deal", [
        'final_terms' => [['label' => '一口价', 'value' => '每户 2.8 万']],
    ])->assertSuccessful();

    $message = sentSubscribeMessages()->sole();
    expect($message['touser'])->toBe($confirmed->openid)
        ->and($message['data']['short_thing3']['value'])->toBe('成交公示');
});

test('posting a progress update notifies participants with the content', function () {
    $initiator = Resident::factory()->create();
    $matter = Matter::factory()->open()->for($initiator, 'initiator')->create();
    $participant = Resident::factory()->create();
    Stance::factory()->for($matter, 'matter')->for($participant, 'resident')->create();

    Sanctum::actingAs($initiator);
    $this->postJson("/api/matters/{$matter->id}/updates", [
        'happened_on' => now()->toDateString(),
        'content' => '已和商家谈妥第一轮价格',
    ])->assertCreated();

    $message = sentSubscribeMessages()->sole();
    expect($message['touser'])->toBe($participant->openid)
        ->and($message['data']['short_thing3']['value'])->toBe('有新进展')
        ->and($message['data']['thing4']['value'])->toBe('已和商家谈妥第一轮价格');
});

test('listing a party notifies its owner and only on the flip', function () {
    $party = Party::factory()->create(['name' => '青城中央空调']);
    $owner = Resident::factory()->create(['affiliated_party_id' => $party->id]);
    Sanctum::actingAs(Resident::factory()->admin()->create());

    $this->putJson("/api/admin/parties/{$party->id}", ['is_approved' => true])->assertSuccessful();

    $message = sentSubscribeMessages()->sole();
    expect($message['touser'])->toBe($owner->openid)
        ->and($message['page'])->toBe("pages/party/index?id={$party->id}")
        ->and($message['data']['thing1']['value'])->toBe('青城中央空调')
        ->and($message['data']['short_thing3']['value'])->toBe('核验通过');

    // 重复保存已核验状态不再通知
    $this->putJson("/api/admin/parties/{$party->id}", ['is_approved' => true])->assertSuccessful();
    expect(sentSubscribeMessages())->toHaveCount(1);
});

test('self-registering a party notifies the admins to certify it', function () {
    $admin = Resident::factory()->admin()->create();
    Sanctum::actingAs(Resident::factory()->create());

    $this->postJson('/api/me/party', ['type' => 'merchant', 'name' => '青城中央空调'])->assertSuccessful();

    $message = sentSubscribeMessages()->sole();
    expect($message['touser'])->toBe($admin->openid)
        ->and($message['data']['thing1']['value'])->toBe('青城中央空调')
        ->and($message['data']['short_thing3']['value'])->toBe('待核验');
});

test('rejecting a party notifies its owner with the reason', function () {
    $party = Party::factory()->create(['name' => '青城中央空调']);
    $owner = Resident::factory()->create(['affiliated_party_id' => $party->id]);
    Sanctum::actingAs(Resident::factory()->admin()->create());

    $this->putJson("/api/admin/parties/{$party->id}", ['is_approved' => false, 'reason' => '请补充营业执照'])
        ->assertSuccessful();

    $message = sentSubscribeMessages()->sole();
    expect($message['touser'])->toBe($owner->openid)
        ->and($message['data']['short_thing3']['value'])->toBe('未通过')
        ->and($message['data']['thing4']['value'])->toBe('请补充营业执照');
});

test('overlong template values are clipped to the field limits', function () {
    $initiator = Resident::factory()->create();
    $matter = Matter::factory()->open()->for($initiator, 'initiator')
        ->create(['title' => str_repeat('装', 25)]);
    Stance::factory()->for($matter, 'matter')->create();

    Sanctum::actingAs($initiator);
    $this->putJson("/api/matters/{$matter->id}/state", ['state' => 'done'])->assertSuccessful();

    $title = sentSubscribeMessages()->sole()['data']['thing1']['value'];
    expect(mb_strlen($title))->toBe(20)
        ->and(mb_substr($title, -1))->toBe('…');
});

test('nothing is sent when no template id is configured', function () {
    config(['services.wechat.subscribe_template_id' => null]);

    $initiator = Resident::factory()->create();
    $matter = Matter::factory()->open()->for($initiator, 'initiator')->create();
    Stance::factory()->for($matter, 'matter')->create();

    Sanctum::actingAs($initiator);
    $this->putJson("/api/matters/{$matter->id}/state", ['state' => 'done'])->assertSuccessful();

    Http::assertNothingSent();
});

test('a wechat failure (quota used up) never breaks the request', function () {
    Http::fake([
        'api.weixin.qq.com/cgi-bin/stable_token' => Http::response(['access_token' => 'fake-token']),
        'api.weixin.qq.com/cgi-bin/message/subscribe/send*' => Http::response(['errcode' => 43101, 'errmsg' => 'user refused']),
    ]);

    $initiator = Resident::factory()->create();
    $matter = Matter::factory()->open()->for($initiator, 'initiator')->create();
    Stance::factory()->for($matter, 'matter')->create();

    Sanctum::actingAs($initiator);
    $this->putJson("/api/matters/{$matter->id}/state", ['state' => 'done'])->assertSuccessful();
});
