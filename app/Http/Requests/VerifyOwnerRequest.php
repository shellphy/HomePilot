<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class VerifyOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'invite_code' => ['required', 'string', 'size:8', 'alpha_num:ascii'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'invite_code' => Str::upper($this->string('invite_code')->trim()->toString()),
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'invite_code.required' => '请填写邀请码',
            'invite_code.size' => '邀请码应为 8 位',
            'invite_code.alpha_num' => '邀请码格式不正确',
        ];
    }
}
