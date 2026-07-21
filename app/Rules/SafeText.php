<?php

namespace App\Rules;

use App\Enums\SecCheckScene;
use App\Models\Resident;
use App\Services\WeChat;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

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
            $fail('内容暂时无法通过安全审核，请稍后重试或修改后再发布。');
        }
    }
}
