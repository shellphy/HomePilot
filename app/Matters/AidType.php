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

    /** 互助没凑成的收场出口：与「已结束」分开，取消的互助不开放评价。 */
    public function abortLabel(): ?string
    {
        return '已取消';
    }

    public function allowsJoin(Matter $matter): bool
    {
        return $matter->state === 'open' && $this->registrationOpen($matter);
    }

    /** 进行中就互通联系方式：拼车、代收不互通电话成不了事。 */
    public function contactsOpen(Matter $matter): bool
    {
        return $matter->state === 'open' && $this->registrationOpen($matter);
    }

    /** 互助结束后开放评价，给后来搭伙的邻居留参考。 */
    public function reviewOpen(Matter $matter): bool
    {
        return $matter->state === 'closed';
    }

    public function sortWeight(Matter $matter): int
    {
        return $matter->state === 'open' ? 2 : 9;
    }
}
