<?php

namespace App\Http\Requests;

use App\Enums\ProjectStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
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
            'category' => ['required', 'string', 'max:30'],
            'title' => ['required', 'string', 'max:60'],
            'status' => ['required', Rule::enum(ProjectStatus::class)],
            'target_households' => ['required', 'integer', 'min:0', 'max:999'],
            'pitch' => ['nullable', 'string', 'max:1000'],
            'perk' => ['nullable', 'string', 'max:100'],
            'terms' => ['nullable', 'array'],
            'terms.*.label' => ['required', 'string', 'max:30'],
            'terms.*.value' => ['required', 'string', 'max:100'],
            'glossary' => ['nullable', 'array'],
            'glossary.*.term' => ['required', 'string', 'max:30'],
            'glossary.*.explain' => ['required', 'string', 'max:300'],
        ];
    }

    /**
     * 入库载荷：把可空字段规整成列的默认形态。
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = $this->validated();
        $data['perk'] = $data['perk'] ?? '';
        $data['terms'] = $data['terms'] ?? [];
        $data['glossary'] = $data['glossary'] ?? [];

        return $data;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category.required' => '请选择品类',
            'title.required' => '请填写标题',
            'target_households.required' => '请填写目标户数',
        ];
    }
}
