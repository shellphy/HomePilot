<?php

namespace App\Matters;

use App\Models\Matter;

/**
 * 集体采购（装修团购）：第一个事务类型，也是产品的引擎。
 */
class GroupbuyType extends MatterType
{
    public function key(): string
    {
        return 'groupbuy';
    }

    public function label(): string
    {
        return '团购';
    }

    public function states(): array
    {
        return [
            'seeking' => '意向征集',
            'negotiating' => '谈判中',
            'open' => '接龙中',
            'done' => '已成团',
        ];
    }

    public function baseRules(): array
    {
        return [
            'category' => ['required', 'string', 'max:30'],
            'target_count' => ['required', 'integer', 'min:0', 'max:999'],
        ];
    }

    public function payloadRules(): array
    {
        return [
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

    public function payloadFrom(array $validated): array
    {
        return [
            'pitch' => $validated['pitch'] ?? '',
            'perk' => $validated['perk'] ?? '',
            'terms' => $validated['terms'] ?? [],
            'glossary' => $validated['glossary'] ?? [],
        ];
    }

    /** 未成团前都可以报名/取消。 */
    public function allowsJoin(Matter $matter): bool
    {
        return $matter->state !== 'done';
    }

    /** 成团后开放评价。 */
    public function reviewOpen(Matter $matter): bool
    {
        return $matter->state === 'done';
    }

    /** 接龙中 > 谈判中 > 意向征集，已成团垫底。 */
    public function sortWeight(Matter $matter): int
    {
        return match ($matter->state) {
            'open' => 0,
            'negotiating' => 1,
            'seeking' => 2,
            default => 9,
        };
    }
}
