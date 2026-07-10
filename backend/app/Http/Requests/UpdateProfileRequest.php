<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
     * 全部字段可清空（传空即清空）；相关方身份走 PartyController。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nickname' => ['sometimes', 'nullable', 'string', 'max:30'],
            'avatar' => ['sometimes', 'url', 'max:255'],
            'unit_label' => ['sometimes', 'nullable', 'string', 'max:30'],
            'room_label' => ['sometimes', 'nullable', 'string', 'max:30'],
            'phone' => ['sometimes', 'nullable', 'string', 'regex:/^1[3-9]\d{9}$/'],
            'wechat_id' => ['sometimes', 'nullable', 'string', 'max:50'],
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
