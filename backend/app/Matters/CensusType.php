<?php

namespace App\Matters;

use App\Models\Matter;

/**
 * 征集/摸底：面向全小区收集结构化表态（mode=register），聚合结果匿名公示。
 * 装修意向摸底是第一个实例；收房问题、车位需求摸底将来同构接入。
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
        ];
    }

    public function payloadFrom(array $validated): array
    {
        return ['pitch' => $validated['pitch'] ?? ''];
    }

    /** 征集由管理端发起。 */
    public function userInitiatable(): bool
    {
        return false;
    }

    /** 征集中置顶于事务流。 */
    public function sortWeight(Matter $matter): int
    {
        return $matter->state === 'open' ? 0 : 9;
    }
}
