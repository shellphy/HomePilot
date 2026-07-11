<?php

namespace App\Matters;

use App\Models\Matter;

/**
 * 互助：拼车、借物、代收、搭伙看工地——凑人即成。
 */
class AidType extends MatterType
{
    public function key(): string
    {
        return 'aid';
    }

    public function label(): string
    {
        return '互助';
    }

    public function states(): array
    {
        return [
            'open' => '进行中',
            'closed' => '已结束',
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
        return $matter->state === 'open';
    }

    public function sortWeight(Matter $matter): int
    {
        return $matter->state === 'open' ? 2 : 9;
    }
}
