<?php

namespace App\Http\Requests;

use App\Enums\SecCheckScene;
use App\Models\Resident;
use App\Rules\SafeText;
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
     * 可选字段可清空（传空即清空）；手机号可微信授权预填或手填，随资料一并保存；相关方身份走 PartyController。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // 业主必须有楼栋号（名单公示按它展示），且只能选社区设置里的楼栋；相关方账号没有楼栋概念，允许空
        $user = $this->user();
        $isOwner = ! $user instanceof Resident || $user->affiliated_party_id === null;
        $settings = app(CommunitySettings::class);

        $nicknameRules = ['sometimes', 'nullable', 'string', 'max:30'];
        if ($user instanceof Resident) {
            $nicknameRules[] = new SafeText($user, SecCheckScene::Profile);
        }

        return [
            'nickname' => $nicknameRules,
            'avatar' => ['sometimes', 'url', 'max:255'],
            // 手机号可微信授权预填、也可手填改成别的联系号码，统一随资料保存；允许清空
            'phone' => ['sometimes', 'nullable', 'string', 'regex:/^(1\d{10})?$/'],
            'unit_label' => $isOwner
                ? ['sometimes', 'required', Rule::in($settings->buildings)]
                : ['sometimes', 'nullable', Rule::in($settings->buildings)],
            'room_label' => ['sometimes', 'nullable', 'string', 'max:30'],
            // 户型选填：AI 答疑按它理解「我家」，管理端登记明细也带上
            'layout_label' => ['sometimes', 'nullable', Rule::in($settings->layouts)],
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
            'layout_label.in' => '请从户型列表里选择',
            'phone.regex' => '请填写正确的手机号',
        ];
    }
}
