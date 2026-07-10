<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nickname' => ['sometimes', 'string', 'max:30'],
            'avatar' => ['sometimes', 'url', 'max:255'],
            'unit_label' => ['sometimes', 'string', 'max:30'],
            'phone' => ['sometimes', 'nullable', 'string', 'regex:/^1[3-9]\d{9}$/'],
            'wechat_id' => ['sometimes', 'string', 'max:50'],
            'role' => ['sometimes', Rule::in(['resident', 'merchant'])],
            'merchant_name' => ['sometimes', 'string', 'max:50'],
            'merchant_category' => ['sometimes', 'string', 'max:30'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => '手机号格式不对，请检查一下',
        ];
    }
}
