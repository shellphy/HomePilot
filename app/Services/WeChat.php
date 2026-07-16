<?php

namespace App\Services;

use App\Enums\SecCheckScene;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class WeChat
{
    /**
     * 用小程序 wx.login 拿到的 code 换身份（code2session）。
     * 小程序绑在开放平台账号下，unionid 随 code2session 一并下发。
     *
     * @return array{openid: string, unionid: string}
     *
     * @throws ValidationException
     */
    public function sessionFromCode(string $code): array
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
        } catch (ConnectionException $e) {
            Log::warning('微信 code2session 连接失败', ['error' => $e->getMessage()]);

            throw ValidationException::withMessages(['code' => '微信登录超时，请重试']);
        }

        $openid = $response->json('openid');
        $unionid = $response->json('unionid');

        // unionid 缺失说明小程序脱离了开放平台账号，认人的锚点没了
        if (! $response->successful() || blank($openid) || blank($unionid)) {
            Log::warning('微信 code2session 未拿到可用身份', [
                ...$this->failureContext($response),
                'has_openid' => filled($openid),
                'has_unionid' => filled($unionid),
            ]);

            throw ValidationException::withMessages(['code' => '微信登录失败，请重试']);
        }

        return ['openid' => $openid, 'unionid' => $unionid];
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
        } catch (ConnectionException $e) {
            Log::warning('微信手机号换取连接失败', ['error' => $e->getMessage()]);

            throw ValidationException::withMessages(['code' => '微信服务超时，请重试']);
        }

        $phone = $response->json('phone_info.purePhoneNumber');

        if (! $response->successful() || (int) $response->json('errcode') !== 0 || blank($phone)) {
            // 手机号本身是 PII，只记拿到没拿到
            Log::warning('微信手机号换取失败', [
                ...$this->failureContext($response),
                'has_phone' => filled($phone),
            ]);

            throw ValidationException::withMessages(['code' => '手机号获取失败，请重新授权']);
        }

        return $phone;
    }

    /**
     * 下发订阅消息（「活动状态提醒」模板）。微信的规则是一次授权一次下发：
     * 额度不足、用户拒收、模板未配置等一律返回 false 不抛——通知是锦上添花，
     * 失败不打扰主流程，站内红点兜底。收件人 openid 是 PII，只记 page。
     *
     * @param  array<string, array{value: string}>  $data
     */
    public function sendSubscribeMessage(string $openidMp, string $page, array $data): bool
    {
        $templateId = config('services.wechat.subscribe_template_id');

        if (blank($templateId) || blank($openidMp)) {
            Log::debug('订阅消息跳过下发', [
                'page' => $page,
                'has_template' => filled($templateId),
                'has_openid_mp' => filled($openidMp),
            ]);

            return false;
        }

        try {
            $response = Http::timeout(5)
                ->connectTimeout(3)
                ->retry([100, 500], throw: false)
                ->post(
                    'https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token='.$this->accessToken(),
                    [
                        'touser' => $openidMp,
                        'template_id' => $templateId,
                        'page' => $page,
                        'data' => $data,
                    ],
                );
        } catch (ConnectionException|ValidationException $e) {
            Log::warning('订阅消息下发中断', ['page' => $page, 'error' => $e->getMessage()]);

            return false;
        }

        if (! $response->successful() || (int) $response->json('errcode') !== 0) {
            // 43101 同时表示用户拒收与额度耗尽，errcode 分不开这两者
            Log::warning('订阅消息下发失败', [...$this->failureContext($response), 'page' => $page]);

            return false;
        }

        return true;
    }

    /**
     * 文本内容安全检测（msgSecCheck v2，同步）。只拦明确违规（suggest=risky），
     * 疑似（review）与正常放行。微信超时或接口报错也放行并记日志：不为机审抖动
     * 堵死发帖，事项本体还有人工审核兜底。
     *
     * openid 用小程序端的 openid_mp，微信要求该用户近两小时内访问过小程序：用户
     * 正在发帖时天然满足，代发/补数据等 openid 为空的场景则跳过检测。
     */
    public function msgSecCheck(string $content, SecCheckScene $scene, string $openidMp): bool
    {
        // 未配置凭证（本地/测试）或拿不到 openid 都无从调起，直接放行
        if (blank(config('services.wechat.appid')) || blank(config('services.wechat.secret'))) {
            return true;
        }

        if (blank($content) || blank($openidMp)) {
            return true;
        }

        // 每次实际发起检测都留痕，方便观测调用量；内容原文与 openid 是敏感数据，只记长度
        Log::info('内容安全检测发起', ['scene' => $scene->value, 'length' => mb_strlen($content)]);

        try {
            $response = Http::timeout(5)
                ->connectTimeout(3)
                ->retry([100, 500], throw: false)
                ->post(
                    'https://api.weixin.qq.com/wxa/msg_sec_check?access_token='.$this->accessToken(),
                    [
                        'version' => 2,
                        'openid' => $openidMp,
                        'scene' => $scene->value,
                        'content' => mb_substr($content, 0, 2500),
                    ],
                );
        } catch (ConnectionException|ValidationException $e) {
            Log::warning('内容安全检测中断', ['scene' => $scene->value, 'error' => $e->getMessage()]);

            return true;
        }

        if (! $response->successful() || (int) $response->json('errcode') !== 0) {
            Log::warning('内容安全检测失败', [...$this->failureContext($response), 'scene' => $scene->value]);

            return true;
        }

        $suggest = $response->json('result.suggest');

        // 检测结果都记 info，pass/review/risky 分布可查；命中原文不入日志，只留分类标签
        Log::info('内容安全检测结果', [
            'scene' => $scene->value,
            'suggest' => $suggest,
            'label' => $response->json('result.label'),
        ]);

        return $suggest !== 'risky';
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
            } catch (ConnectionException $e) {
                Log::warning('微信 access_token 连接失败', ['error' => $e->getMessage()]);

                throw ValidationException::withMessages(['code' => '微信服务超时，请重试']);
            }

            $token = $response->json('access_token');

            if (! $response->successful() || blank($token)) {
                // token 拿不到会连带拖垮所有订阅消息
                Log::error('微信 access_token 获取失败', $this->failureContext($response));

                throw ValidationException::withMessages(['code' => '微信服务暂时不可用，请稍后再试']);
            }

            return $token;
        });
    }

    /**
     * 微信业务错误也返回 HTTP 200，errcode 只在 body 里。
     *
     * @return array{status: int, errcode: mixed, errmsg: mixed}
     */
    private function failureContext(Response $response): array
    {
        return [
            'status' => $response->status(),
            'errcode' => $response->json('errcode'),
            'errmsg' => $response->json('errmsg'),
        ];
    }
}
