<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRegistrationRequest extends FormRequest
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
            'layout' => ['required', Rule::in(config('homepilot.layouts'))],
            'decoration_mode' => ['required', Rule::in(config('homepilot.decoration_modes'))],
            'interests' => ['required', 'array', 'min:1'],
            'interests.*' => [Rule::in(config('homepilot.categories'))],
            'wechat_id' => ['required', 'string', 'max:50'],
            'phone' => ['sometimes', 'nullable', 'string', 'regex:/^1[3-9]\d{9}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'layout.required' => '请选择你家的户型',
            'decoration_mode.required' => '请选择装修方式',
            'interests.required' => '至少选一个感兴趣的团购品类',
            'wechat_id.required' => '请填写微信号，方便团购联系',
            'phone.regex' => '手机号格式不对，请检查一下',
        ];
    }
}
