<?php

use App\Models\Resident;
use Illuminate\Support\Facades\Http;

test('login exchanges the wechat code for a token and creates the resident', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.weixin.qq.com/*' => Http::response(['openid' => 'openid-abc', 'session_key' => 'ignored']),
    ]);

    $response = $this->postJson('/api/login', ['code' => 'valid-code'])
        ->assertSuccessful();

    expect($response->json('token'))->toBeString()->not->toBeEmpty();
    expect(Resident::where('openid', 'openid-abc')->count())->toBe(1);
});

test('logging in twice with the same openid reuses the resident', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.weixin.qq.com/*' => Http::response(['openid' => 'openid-abc', 'session_key' => 'ignored']),
    ]);

    $this->postJson('/api/login', ['code' => 'first'])->assertSuccessful();
    $this->postJson('/api/login', ['code' => 'second'])->assertSuccessful();

    expect(Resident::where('openid', 'openid-abc')->count())->toBe(1);
});

test('login fails with a friendly validation message when wechat rejects the code', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.weixin.qq.com/*' => Http::response(['errcode' => 40029, 'errmsg' => 'invalid code']),
    ]);

    $this->postJson('/api/login', ['code' => 'bad-code'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');

    expect(Resident::count())->toBe(0);
});

test('login requires a code', function () {
    $this->postJson('/api/login', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');
});

test('unauthenticated api requests get a 401 json even without an accept header', function () {
    // 回归：曾因默认重定向到不存在的 login 路由而 500
    $this->get('/api/me')->assertUnauthorized();
});
