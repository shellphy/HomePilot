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

    /** @throws ValidationException */
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
            Log::warning('微信手机号换取失败', [
                ...$this->failureContext($response),
                'has_phone' => filled($phone),
            ]);

            throw ValidationException::withMessages(['code' => '手机号获取失败，请重新授权']);
        }

        return $phone;
    }

    /**
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
            Log::warning('订阅消息下发失败', [...$this->failureContext($response), 'page' => $page]);

            return false;
        }

        return true;
    }

    public function msgSecCheck(string $content, SecCheckScene $scene, string $openidMp): bool
    {
        if (blank(config('services.wechat.appid')) || blank(config('services.wechat.secret'))) {
            Log::error('内容安全检测未配置微信凭证', [
                'scene' => $scene->value,
            ]);

            return false;
        }

        if (blank($openidMp)) {
            Log::warning('内容安全检测缺少用户 openid', ['scene' => $scene->value]);

            return false;
        }

        try {
            $response = $this->requestMsgSecCheck($content, $scene, $openidMp);

            if ($this->hasInvalidAccessToken($response)) {
                Log::info('微信 access_token 失效，刷新后重试内容安全检测', [
                    'scene' => $scene->value,
                ]);
                Cache::forget('wechat.access_token');
                $response = $this->requestMsgSecCheck($content, $scene, $openidMp);
            }
        } catch (ConnectionException|ValidationException $e) {
            Log::warning('内容安全检测中断', ['scene' => $scene->value, 'error' => $e->getMessage()]);

            return false;
        }

        if (! $response->successful() || (int) $response->json('errcode') !== 0) {
            Log::warning('内容安全检测失败', [...$this->failureContext($response), 'scene' => $scene->value]);

            return false;
        }

        $suggest = $response->json('result.suggest');

        if (! in_array($suggest, ['pass', 'review', 'risky'], true)) {
            Log::warning('内容安全检测返回未知结果', [
                'scene' => $scene->value,
                'suggest' => $suggest,
                'label' => $response->json('result.label'),
            ]);

            return false;
        }

        Log::info('内容安全检测结果', [
            'scene' => $scene->value,
            'suggest' => $suggest,
            'label' => $response->json('result.label'),
            'length' => mb_strlen($content),
        ]);

        return $suggest !== 'risky';
    }

    private function requestMsgSecCheck(string $content, SecCheckScene $scene, string $openidMp): Response
    {
        return Http::timeout(5)
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
    }

    private function hasInvalidAccessToken(Response $response): bool
    {
        return in_array((int) $response->json('errcode'), [40001, 40014, 42001], true);
    }

    /** @throws ValidationException */
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
                Log::error('微信 access_token 获取失败', $this->failureContext($response));

                throw ValidationException::withMessages(['code' => '微信服务暂时不可用，请稍后再试']);
            }

            return $token;
        });
    }

    /**
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
