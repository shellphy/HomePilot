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

    /** 活动办不成的收场出口：与「已结束」分开，取消的活动不开放评价。 */
    public function abortLabel(): ?string
    {
        return '已取消';
    }

    /** 已核验商家可以发起探店、开放日类活动。 */
    public function merchantInitiatable(): bool
    {
        return true;
    }

    public function allowsJoin(Matter $matter): bool
    {
        return $matter->state === 'open' && $this->registrationOpen($matter);
    }

    /**
     * 报名中就互通联系方式（发起人 ↔ 同意共享的报名者）：
     * 拉群、约集合时间都发生在活动开始前，等结束了互通就没意义了。
     */
    public function contactsOpen(Matter $matter): bool
    {
        return $matter->state === 'open' && $this->registrationOpen($matter);
    }

    /** 活动结束后开放评价：口碑沉淀不只属于团购（商家办的活动也计入其档案）。 */
    public function reviewOpen(Matter $matter): bool
    {
        return $matter->state === 'done';
    }

    public function sortWeight(Matter $matter): int
    {
        return $matter->state === 'open' ? 1 : 9;
    }
}
