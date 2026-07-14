<?php

namespace App\Matters;

use App\Models\Matter;

/**
 * 维权行动：对开发商/物业的集体发声——联名即力量。
 */
class RightsType extends MatterType
{
    public function key(): string
    {
        return 'rights';
    }

    public function label(): string
    {
        return '维权';
    }

    public function states(): array
    {
        return [
            'collecting' => '联名征集',
            'negotiating' => '交涉中',
            'resolved' => '已有结果',
        ];
    }

    public function baseRules(): array
    {
        return [
            'target_count' => ['sometimes', 'integer', 'min:0', 'max:999'],
        ];
    }

    public function payloadRules(): array
    {
        return [];
    }

    /** 牵头人不再推进时的收场出口：与「已有结果」分开，不了了之也要有个交代。 */
    public function abortLabel(): ?string
    {
        return '已终止';
    }

    public function allowsJoin(Matter $matter): bool
    {
        return ! $this->isFinalState($matter->state);
    }

    /** 联名名单不对外公示（怕被针对是联名的最大心理门槛），对外只给计数，明细仅牵头人可见。 */
    public function rosterPublic(Matter $matter): bool
    {
        return false;
    }

    public function sortWeight(Matter $matter): int
    {
        return $this->isFinalState($matter->state) ? 9 : 1;
    }
}
