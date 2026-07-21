<?php

use App\Models\Resident;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

test('login exchanges the wechat code for a token and creates the resident', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.weixin.qq.com/*' => Http::response([
            'openid' => 'openid-abc',
            'unionid' => 'unionid-abc',
            'session_key' => 'ignored',
        ]),
    ]);

    $response = $this->postJson('/api/login', ['code' => 'valid-code'])
        ->assertSuccessful();

    expect($response->json('token'))->toBeString()->not->toBeEmpty();

    $resident = Resident::sole();
    expect($resident->unionid)->toBe('unionid-abc');
    expect($resident->openid_mp)->toBe('openid-abc');
});

test('logging in twice with the same unionid reuses the resident', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.weixin.qq.com/*' => Http::response([
            'openid' => 'openid-abc',
            'unionid' => 'unionid-abc',
            'session_key' => 'ignored',
        ]),
    ]);

    $this->postJson('/api/login', ['code' => 'first'])->assertSuccessful();
    $this->postJson('/api/login', ['code' => 'second'])->assertSuccessful();

    expect(Resident::where('unionid', 'unionid-abc')->count())->toBe(1);
});

test('an existing resident known only by unionid gets their miniprogram openid filled in', function () {
    $resident = Resident::factory()->create(['unionid' => 'unionid-abc', 'openid_mp' => '']);

    Http::preventStrayRequests();
    Http::fake([
        'api.weixin.qq.com/*' => Http::response([
            'openid' => 'openid-abc',
            'unionid' => 'unionid-abc',
            'session_key' => 'ignored',
        ]),
    ]);

    $this->postJson('/api/login', ['code' => 'valid-code'])->assertSuccessful();

    expect(Resident::count())->toBe(1);
    expect($resident->refresh()->openid_mp)->toBe('openid-abc');
});

test('login fails when wechat returns no unionid', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.weixin.qq.com/*' => Http::response(['openid' => 'openid-abc', 'session_key' => 'ignored']),
    ]);

    $this->postJson('/api/login', ['code' => 'valid-code'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');

    expect(Resident::count())->toBe(0);
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
    $this->get('/api/me')->assertUnauthorized();
});

test('an authenticated resident can log out the current access token', function () {
    $resident = Resident::factory()->create();
    $token = $resident->createToken('miniprogram');
    Log::spy();

    $this->withToken($token->plainTextToken)->postJson('/api/logout')->assertSuccessful();

    expect($resident->tokens()->count())->toBe(0);
    Log::shouldHaveReceived('info')->with('退出登录', ['resident_id' => $resident->id]);
});

test('login attempts are rate limited by client address', function () {
    foreach (range(1, 30) as $attempt) {
        $this->postJson('/api/login')->assertUnprocessable();
    }

    $this->postJson('/api/login')->assertTooManyRequests();
});
