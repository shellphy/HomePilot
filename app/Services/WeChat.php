<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
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

    /**
     * 用手机号授权组件（open-type="getPhoneNumber"）拿到的 code 换手机号。
     *
     * @throws ValidationException
     */
    public function phoneNumberFromCode(string $code): string
    {
        try {
            $response = Http::timeout(5)
                ->connectTimeout(3)
                ->retry([100, 500], throw: false)
                ->post(
                    'https://api.weixin.qq.com/wxa/business/getuserphonenumber?access_token='.$this->accessToken(),
                    ['code' => $code],
                );
        } catch (ConnectionException) {
            throw ValidationException::withMessages(['code' => '微信服务超时，请重试']);
        }

        $phone = $response->json('phone_info.purePhoneNumber');

        if (! $response->successful() || (int) $response->json('errcode') !== 0 || blank($phone)) {
            throw ValidationException::withMessages(['code' => '手机号获取失败，请重新授权']);
        }

        return $phone;
    }

    /**
     * 下发订阅消息（「活动状态提醒」模板）。微信的规则是一次授权一次下发：
     * 额度不足、用户拒收、模板未配置等一律静默返回 false——通知是锦上添花，
     * 失败不打扰主流程，站内红点兜底。
     *
     * @param  array<string, array{value: string}>  $data
     */
    public function sendSubscribeMessage(string $openid, string $page, array $data): bool
    {
        $templateId = config('services.wechat.subscribe_template_id');

        if (blank($templateId) || blank($openid)) {
            return false;
        }

        try {
            $response = Http::timeout(5)
                ->connectTimeout(3)
                ->retry([100, 500], throw: false)
                ->post(
                    'https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token='.$this->accessToken(),
                    [
                        'touser' => $openid,
                        'template_id' => $templateId,
                        'page' => $page,
                        'data' => $data,
                    ],
                );
        } catch (ConnectionException|ValidationException) {
            return false;
        }

        return $response->successful() && (int) $response->json('errcode') === 0;
    }

    /**
     * 服务端接口调用凭证（stable_token，普通模式下重复获取返回同一个 token）。
     *
     * @throws ValidationException
     */
    private function accessToken(): string
    {
        return Cache::remember('wechat.access_token', now()->addMinutes(100), function (): string {
            try {
                $response = Http::timeout(5)
                    ->connectTimeout(3)
                    ->retry([100, 500], throw: false)
                    ->post('https://api.weixin.qq.com/cgi-bin/stable_token', [
                        'grant_type' => 'client_credential',
                        'appid' => config('services.wechat.appid'),
                        'secret' => config('services.wechat.secret'),
                    ]);
            } catch (ConnectionException) {
                throw ValidationException::withMessages(['code' => '微信服务超时，请重试']);
            }

            $token = $response->json('access_token');

            if (! $response->successful() || blank($token)) {
                throw ValidationException::withMessages(['code' => '微信服务暂时不可用，请稍后再试']);
            }

            return $token;
        });
    }
}
