<?php

use App\Models\Matter;
use App\Models\Resident;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Cache::forget('wechat.access_token');
    config([
        'services.wechat.appid' => 'test-appid',
        'services.wechat.secret' => 'test-secret',
    ]);

    Http::fake([
        'api.weixin.qq.com/cgi-bin/stable_token' => Http::response(['access_token' => 'fake-token']),
    ]);
});

/**
 * 把内容安全接口的判定固定成某个 suggest（risky/pass/review），换 token 的请求单独放行。
 */
function fakeMsgSecCheck(string $suggest): void
{
    Http::fake([
        'api.weixin.qq.com/cgi-bin/stable_token' => Http::response(['access_token' => 'fake-token']),
        'api.weixin.qq.com/wxa/msg_sec_check*' => Http::response([
            'errcode' => 0,
            'result' => ['suggest' => $suggest, 'label' => 20002],
        ]),
    ]);
}

test('命中违规（risky）拦下提问，内容不入库', function () {
    fakeMsgSecCheck('risky');
    $matter = Matter::factory()->create();

    Sanctum::actingAs(Resident::factory()->create());

    $this->postJson("/api/matters/{$matter->id}/questions", ['content' => '违规内容'])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('content');

    expect($matter->questions()->count())->toBe(0);
});

test('疑似（review）放行', function () {
    fakeMsgSecCheck('review');
    $matter = Matter::factory()->create();

    Sanctum::actingAs(Resident::factory()->create());

    $this->postJson("/api/matters/{$matter->id}/questions", ['content' => '这个团怎么参加'])
        ->assertCreated();

    expect($matter->questions()->count())->toBe(1);
});

test('正常（pass）放行', function () {
    fakeMsgSecCheck('pass');
    $matter = Matter::factory()->create();

    Sanctum::actingAs(Resident::factory()->create());

    $this->postJson("/api/matters/{$matter->id}/questions", ['content' => '这个团怎么参加'])
        ->assertCreated();
});

test('微信接口报错时拒绝本次提交，避免未审核内容公开', function () {
    Http::fake([
        'api.weixin.qq.com/cgi-bin/stable_token' => Http::response(['access_token' => 'fake-token']),
        'api.weixin.qq.com/wxa/msg_sec_check*' => Http::response([], 500),
    ]);
    $matter = Matter::factory()->create();

    Sanctum::actingAs(Resident::factory()->create());

    $this->postJson("/api/matters/{$matter->id}/questions", ['content' => '任意内容'])
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('content');
});

test('access token 失效时清除缓存并重试一次内容审核', function () {
    Http::fake([
        'api.weixin.qq.com/cgi-bin/stable_token' => Http::sequence()
            ->push(['access_token' => 'expired-token'])
            ->push(['access_token' => 'fresh-token']),
        'api.weixin.qq.com/wxa/msg_sec_check*' => Http::sequence()
            ->push(['errcode' => 42001, 'errmsg' => 'access_token expired'])
            ->push(['errcode' => 0, 'result' => ['suggest' => 'pass', 'label' => 100]], 200),
    ]);
    $matter = Matter::factory()->create();
    Sanctum::actingAs(Resident::factory()->create());

    $this->postJson("/api/matters/{$matter->id}/questions", ['content' => '正常内容'])
        ->assertCreated();

    Http::assertSentCount(4);
});

test('用户没有小程序 openid 时跳过检测，直接放行', function () {
    // openid 为空则连接口都不调，即便内容会被判违规也放行
    fakeMsgSecCheck('risky');
    $matter = Matter::factory()->create();

    Sanctum::actingAs(Resident::factory()->create(['openid_mp' => '']));

    $this->postJson("/api/matters/{$matter->id}/questions", ['content' => '违规内容'])
        ->assertCreated();

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'msg_sec_check'));
});
