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
     * 全部字段可清空（传空即清空）；相关方身份走 PartyController。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // 业主必须有楼栋号（名单公示按它展示）；相关方账号没有楼栋概念，允许空
        $isOwner = $this->user()?->affiliated_party_id === null;

        return [
            'nickname' => ['sometimes', 'nullable', 'string', 'max:30'],
            'avatar' => ['sometimes', 'url', 'max:255'],
            'unit_label' => $isOwner
                ? ['sometimes', 'required', 'string', 'max:30']
                : ['sometimes', 'nullable', 'string', 'max:30'],
            'room_label' => ['sometimes', 'nullable', 'string', 'max:30'],
            'phone' => ['sometimes', 'nullable', 'string', 'regex:/^1[3-9]\d{9}$/'],
            // 微信号全小区唯一（admin:grant 等场景按它定位人）
            'wechat_id' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                Rule::unique('residents', 'wechat_id')->ignore($this->user()?->getAuthIdentifier()),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => '手机号格式不对，请检查一下',
            'wechat_id.unique' => '这个微信号已被其他邻居填写，请检查是否填错',
            'unit_label.required' => '业主需要填写楼栋号',
        ];
    }
}
