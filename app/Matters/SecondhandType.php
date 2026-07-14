<?php

namespace App\Matters;

use App\Models\Matter;

/**
 * 二手闲置：邻里转让闲置物品——挂个价、传张图，有意向的邻居报名后与卖家互通联系方式。
 */
class SecondhandType extends MatterType
{
    public function key(): string
    {
        return 'secondhand';
    }

    public function label(): string
    {
        return '闲置';
    }

    public function states(): array
    {
        return [
            'open' => '在售',
            'done' => '已出手',
        ];
    }

    public function payloadRules(): array
    {
        return [
            // 价格自由文本：允许「面议」「免费送」这类非数字
            'price' => ['nullable', 'string', 'max:50'],
            // 成色：全新 / 9 成新 / 有使用痕迹…
            'condition' => ['nullable', 'string', 'max:50'],
            'images' => ['sometimes', 'array', 'max:9'],
            'images.*' => ['string', 'max:300'],
        ];
    }

    /** @param array<string, mixed> $validated */
    public function payloadFrom(array $validated): array
    {
        return [
            'price' => $validated['price'] ?? '',
            'condition' => $validated['condition'] ?? '',
            'images' => array_values($validated['images'] ?? []),
        ];
    }

    /** 卖不出去的收场出口：与「已出手」分开，下架的闲置不开放评价。 */
    public function abortLabel(): ?string
    {
        return '已下架';
    }

    public function allowsJoin(Matter $matter): bool
    {
        return $matter->state === 'open' && $this->registrationOpen($matter);
    }

    /** 在售期间就互通联系方式：有意向的邻居直接和卖家聊。 */
    public function contactsOpen(Matter $matter): bool
    {
        return $matter->state === 'open' && $this->registrationOpen($matter);
    }

    public function sortWeight(Matter $matter): int
    {
        return $matter->state === 'open' ? 3 : 9;
    }
}
