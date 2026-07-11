<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class WeChat
{
    /**
     * 用小程序 wx.login 拿到的 code 换 openid（code2session）。
     *
     * @throws ValidationException
     */
    public function openidFromCode(string $code): string
    {
        try {
            $response = Http::timeout(5)
                ->connectTimeout(3)
                ->retry([100, 500], throw: false)
                ->get('https://api.weixin.qq.com/sns/jscode2session', [
                    'appid' => config('services.wechat.appid'),
                    'secret' => config('services.wechat.secret'),
                    'js_code' => $code,
                    'grant_type' => 'authorization_code',
                ]);
        } catch (ConnectionException) {
            throw ValidationException::withMessages(['code' => '微信登录超时，请重试']);
        }

        $openid = $response->json('openid');

        if (! $response->successful() || blank($openid)) {
            throw ValidationException::withMessages(['code' => '微信登录失败，请重试']);
        }

        return $openid;
    }
}
