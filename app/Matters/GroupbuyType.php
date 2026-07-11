<?php

namespace App\Matters;

use App\Models\Matter;
use App\Models\Resident;
use App\Models\Stance;

/**
 * 集体采购（装修团购）。
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
            // 团购没有「不设目标」：目标人数是去谈价的筹码，至少 1 人（业主端与管理端同一口径）
            'target_count' => ['required', 'integer', 'min:1', 'max:999'],
        ];
    }

    /** 谈判失败/人数不够的收场出口：不触发联系互通与评价。 */
    public function abortLabel(): ?string
    {
        return '未成团';
    }

    public function payloadRules(): array
    {
        return [
            'pitch' => ['nullable', 'string', 'max:1000'],
            'perk' => ['nullable', 'string', 'max:100'],
            // 方案型团购（如中央空调、全屋定制）：非标品，商家需逐户沟通需求（如上门量房）
            // 单独出方案，联系互通提前到谈判中
            'needs_survey' => ['sometimes', 'boolean'],
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
            'needs_survey' => (bool) ($validated['needs_survey'] ?? false),
        ];
    }

    /** 方案型开关只在发起时选：中途翻转会改变联系互通的隐私承诺。 */
    public function lockedPayloadKeys(): array
    {
        return ['needs_survey'];
    }

    /** 方案型团购：非标品（中央空调/全屋定制），每户方案与成交价不同。 */
    public function needsSurvey(Matter $matter): bool
    {
        return (bool) $matter->payloadValue('needs_survey', false);
    }

    /** 已认证商家可以发起商家直供团。 */
    public function merchantInitiatable(): bool
    {
        return true;
    }

    /** 收场（已成团/未成团）前都可以报名/取消。 */
    public function allowsJoin(Matter $matter): bool
    {
        return ! $this->isFinalState($matter->state);
    }

    /**
     * 报名的承诺档位：接龙中报名 = 确认参团；意向征集/谈判中报名 = 登记意向
     * （条款没谈出来前只是兴趣，进入接龙中后要本人再确认一次才算数）。
     */
    public function joinStage(Matter $matter): ?string
    {
        return $matter->state === 'open' ? Stance::JOIN_STAGE_CONFIRMED : Stance::JOIN_STAGE_INTENT;
    }

    /** 成团后开放评价（只算确认参团的人）。 */
    public function reviewOpen(Matter $matter): bool
    {
        return $matter->state === 'done';
    }

    /** 参与过 = 确认参团过；只登记过意向的不算（没进成交名单，评价资格也不该有）。 */
    public function isParticipant(Matter $matter, Resident $resident): bool
    {
        return $matter->confirmedJoins()->where('resident_id', $resident->id)->exists();
    }

    /**
     * 联系方式互通阶段：标品团成团后才互通（建群、收款在此后发生）；
     * 方案型团从谈判中开始（商家不先逐户沟通——量房、看户型——就出不了方案，
     * 业主拿不到自家报价没法决定参团）。
     */
    public function contactsOpen(Matter $matter): bool
    {
        return $this->needsSurvey($matter)
            ? in_array($matter->state, ['negotiating', 'open', 'done'], true)
            : $matter->state === 'done';
    }

    /**
     * 标品团只跟确认参团的人互通（登记过意向不等于进了成交名单）；
     * 方案型团里报名本身就是约商家出方案，意向档也在互通名单里。
     */
    public function contactEligible(Matter $matter, Stance $join): bool
    {
        return $this->needsSurvey($matter) || $join->joinStageValue() !== Stance::JOIN_STAGE_INTENT;
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
