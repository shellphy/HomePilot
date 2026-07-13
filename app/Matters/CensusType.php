<?php

namespace App\Matters;

use App\Models\Matter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * 征集/摸底：面向全小区收集结构化表态（mode=register），聚合结果匿名公示。
 * 业主与管理员都可发起；参与走登记表态而不是接龙。
 */
class CensusType extends MatterType
{
    public function key(): string
    {
        return 'census';
    }

    public function label(): string
    {
        return '征集';
    }

    public function states(): array
    {
        return [
            'open' => '征集中',
            'closed' => '已结束',
        ];
    }

    public function payloadRules(): array
    {
        return [
            'pitch' => ['nullable', 'string', 'max:1000'],
            // 发起目的：自由文本，发起者写为什么发这次征集，给参与者看；不枚举、不驱动分支
            'purpose' => ['nullable', 'string', 'max:1000'],
            // 署名发起：物业/业委会/商家想做的调研由管理员代建，结果对全小区公开
            'collects_contact' => ['sometimes', 'boolean'],
            'modules' => ['sometimes', 'array'],
            'modules.*.key' => ['sometimes', 'string', 'max:30'],
            'modules.*.title' => ['required', 'string', 'max:30'],
            'modules.*.intro' => ['sometimes', 'nullable', 'string', 'max:200'],
            // 允许空模块：小程序端「先建模块再逐题添加」的中间态；业主端渲染时跳过
            'modules.*.questions' => ['sometimes', 'array'],
            'modules.*.questions.*.key' => ['sometimes', 'string', 'max:30'],
            'modules.*.questions.*.text' => ['required', 'string', 'max:100'],
            'modules.*.questions.*.type' => ['required', Rule::in(['single', 'multi', 'text'])],
            'modules.*.questions.*.note' => ['sometimes', 'nullable', 'string', 'max:200'],
            // 填空题没有选项（前端不传该键）；选择题至少两个
            'modules.*.questions.*.options' => ['required_unless:modules.*.questions.*.type,text', 'array', 'min:2'],
            'modules.*.questions.*.options.*' => ['required', 'string', 'max:50'],
            // 选项解释（与 options 平行的数组，答案仍只存选项本身）：答题即建概念
            'modules.*.questions.*.option_notes' => ['sometimes', 'array'],
            'modules.*.questions.*.option_notes.*' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * 取出 payload 并给模块/题目自动补 key（答案按 key 存，缺失时生成、已有的不动）。
     */
    public function payloadFrom(array $validated): array
    {
        $payload = [
            'pitch' => $validated['pitch'] ?? '',
            'purpose' => $validated['purpose'] ?? '',
        ];

        if (array_key_exists('collects_contact', $validated)) {
            $payload['collects_contact'] = (bool) $validated['collects_contact'];
        }

        if (isset($validated['modules']) && is_array($validated['modules'])) {
            $payload['modules'] = collect($validated['modules'])
                ->map(function (array $module): array {
                    $module['key'] = $module['key'] ?? 'm_'.Str::lower(Str::random(6));
                    $questions = $module['questions'] ?? [];
                    $module['questions'] = collect(is_array($questions) ? $questions : [])
                        ->map(function (array $question): array {
                            $question['key'] = $question['key'] ?? 'q_'.Str::lower(Str::random(6));

                            return $question;
                        })
                        ->all();

                    return $module;
                })
                ->all();
        }

        return $payload;
    }

    /** 征集中置顶于事项流。 */
    public function sortWeight(Matter $matter): int
    {
        return $matter->state === 'open' ? 0 : 9;
    }
}
