<?php

namespace App\Matters;

use App\Models\Matter;

/**
 * 征集/摸底：面向全小区收集结构化表态（mode=register），聚合结果匿名公示。
 * 由管理员发起；参与走登记表态而不是接龙。
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
        ];
    }

    public function payloadFrom(array $validated): array
    {
        return [
            'pitch' => $validated['pitch'] ?? '',
            'purpose' => $validated['purpose'] ?? '',
        ];
    }

    /** 征集由管理端发起。 */
    public function userInitiatable(): bool
    {
        return false;
    }

    /** 征集中置顶于事项流。 */
    public function sortWeight(Matter $matter): int
    {
        return $matter->state === 'open' ? 0 : 9;
    }
}
