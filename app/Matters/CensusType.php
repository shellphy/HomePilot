<?php

namespace App\Matters;

use App\Models\Matter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** 征集：已认证用户提交结构化表态，聚合结果匿名公示。 */
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
            'purpose' => ['nullable', 'string', 'max:1000'],
            'collects_contact' => ['sometimes', 'boolean'],
            'modules' => ['sometimes', 'array'],
            'modules.*' => ['array:key,title,questions'],
            'modules.*.key' => ['sometimes', 'string', 'max:30'],
            'modules.*.title' => ['required', 'string', 'max:30'],
            // 允许空模块：小程序端「先建模块再逐题添加」的中间态；业主端渲染时跳过
            'modules.*.questions' => ['sometimes', 'array'],
            'modules.*.questions.*' => ['array:key,text,type,options'],
            'modules.*.questions.*.key' => ['sometimes', 'string', 'max:30'],
            'modules.*.questions.*.text' => ['required', 'string', 'max:100'],
            'modules.*.questions.*.type' => ['required', Rule::in(['single', 'multi', 'text'])],
            // 填空题没有选项（前端不传该键）；选择题至少两个
            'modules.*.questions.*.options' => ['required_unless:modules.*.questions.*.type,text', 'array', 'min:2'],
            'modules.*.questions.*.options.*' => ['required', 'string', 'max:50'],
        ];
    }

    /**
     * 取出 payload 并给模块/题目自动补 key（答案按 key 存，缺失时生成、已有的不动）。
     *
     * @param  array{purpose?: string|null, collects_contact?: bool, modules?: array<int, array{key?: string, title: string, questions?: array<int, array{key?: string, text: string, type: string, options?: array<int, string>}>}>}  $validated
     */
    public function payloadFrom(array $validated): array
    {
        $payload = [
            'purpose' => $validated['purpose'] ?? '',
        ];

        if (array_key_exists('collects_contact', $validated)) {
            $payload['collects_contact'] = (bool) $validated['collects_contact'];
        }

        if (isset($validated['modules'])) {
            $payload['modules'] = collect($validated['modules'])
                ->map(function (array $module): array {
                    $module['key'] = $module['key'] ?? 'm_'.Str::lower(Str::random(6));
                    $module['questions'] = collect($module['questions'] ?? [])
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
