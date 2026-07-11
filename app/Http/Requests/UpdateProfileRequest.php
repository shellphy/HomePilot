<?php

namespace App\Http\Requests;

use App\Models\Resident;
use App\Settings\CommunitySettings;
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
     * 可选字段可清空（传空即清空）；手机号走授权接口（/me/phone），相关方身份走 PartyController。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // 业主必须有楼栋号（名单公示按它展示），且只能选社区设置里的楼栋；相关方账号没有楼栋概念，允许空
        $user = $this->user();
        $isOwner = ! $user instanceof Resident || $user->affiliated_party_id === null;
        $buildings = app(CommunitySettings::class)->buildings;

        return [
            'nickname' => ['sometimes', 'nullable', 'string', 'max:30'],
            'avatar' => ['sometimes', 'url', 'max:255'],
            'unit_label' => $isOwner
                ? ['sometimes', 'required', Rule::in($buildings)]
                : ['sometimes', 'nullable', Rule::in($buildings)],
            'room_label' => ['sometimes', 'nullable', 'string', 'max:30'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'unit_label.required' => '业主需要选择楼栋号',
            'unit_label.in' => '请从楼栋列表里选择',
        ];
    }
}
