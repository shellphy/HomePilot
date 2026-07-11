<?php

namespace App\Matters;

use App\Models\Matter;

/**
 * 维权行动：对开发商/物业的集体发声——联名即力量。
 * 这是"登记→接龙→公示"三件套的正赛预演。
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
        return [
            'pitch' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function payloadFrom(array $validated): array
    {
        return ['pitch' => $validated['pitch'] ?? ''];
    }

    public function allowsJoin(Matter $matter): bool
    {
        return $matter->state !== 'resolved';
    }

    /** 联名名单不对外公示（怕被针对是联名的最大心理门槛），对外只给计数，明细仅牵头人可见。 */
    public function rosterPublic(Matter $matter): bool
    {
        return false;
    }

    public function sortWeight(Matter $matter): int
    {
        return $matter->state === 'resolved' ? 9 : 1;
    }
}
