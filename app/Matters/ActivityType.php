<?php

namespace App\Matters;

use App\Models\Matter;

/**
 * 邻里活动：见面会、组团踩点、球局——报名即参加。
 */
class ActivityType extends MatterType
{
    public function key(): string
    {
        return 'activity';
    }

    public function label(): string
    {
        return '活动';
    }

    public function states(): array
    {
        return [
            'open' => '报名中',
            'done' => '已结束',
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

    /** 已认证商家可以发起探店、开放日类活动。 */
    public function merchantInitiatable(): bool
    {
        return true;
    }

    public function allowsJoin(Matter $matter): bool
    {
        return $matter->state === 'open';
    }

    public function sortWeight(Matter $matter): int
    {
        return $matter->state === 'open' ? 1 : 9;
    }
}
