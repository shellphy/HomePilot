<?php

namespace App\Rules;

use App\Enums\SecCheckScene;
use App\Models\Resident;
use App\Services\WeChat;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * 用户提交的文本走微信内容安全检测，命中违规即校验失败。
 */
class SafeText implements ValidationRule
{
    public function __construct(
        private Resident $resident,
        private SecCheckScene $scene,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || blank($value)) {
            return;
        }

        if (! app(WeChat::class)->msgSecCheck($value, $this->scene, $this->resident->openid_mp)) {
            $fail('内容包含违规信息，请修改后再发布。');
        }
    }
}
