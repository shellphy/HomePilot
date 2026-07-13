<?php

namespace App\Matters;

use App\Models\Matter;
use App\Models\Resident;
use App\Models\Stance;

/**
 * 事项类型：定义一类社区事项装载哪些能力——状态机、payload 校验、
 * 表态模式开关、列表排序权重。新场景 = 新增一个子类，不新建表、不新建端点。
 */
abstract class MatterType
{
    /** 旁路终态的统一 key：半途收场（未成团/已取消）都落在这个状态上。 */
    public const ABORT_STATE = 'aborted';

    /** 类型标识，如 groupbuy。 */
    abstract public function key(): string;

    /** 类型显示名，如 团购。 */
    abstract public function label(): string;

    /**
     * 状态机：state => 显示名，第一个为初始状态。
     *
     * @return array<string, string>
     */
    abstract public function states(): array;

    /**
     * payload 的验证规则（键不带 payload. 前缀）。
     *
     * @return array<string, array<int, mixed>>
     */
    abstract public function payloadRules(): array;

    /**
     * 列级字段（category/target_count）的验证规则，类型可覆盖。
     *
     * @return array<string, array<int, mixed>>
     */
    public function baseRules(): array
    {
        return [];
    }

    /**
     * 事项正文（body 列）的验证规则；类型可覆盖是否必填与长度上限。
     *
     * @return array<int, mixed>
     */
    public function bodyRules(): array
    {
        return ['nullable', 'string', 'max:1000'];
    }

    /** 业主是否可以从小程序发起该类型事项（否=仅管理端创建）。 */
    public function userInitiatable(): bool
    {
        return true;
    }

    /** 已认证商家是否可以发起该类型事项（带商家署名与「已认证」标识）。 */
    public function merchantInitiatable(): bool
    {
        return false;
    }

    /** 是否出现在小区事项流里。 */
    public function visibleInList(Matter $matter): bool
    {
        return true;
    }

    /** 是否开放「大家都在问」公开问答（业主提问、负责方回答）。 */
    public function supportsQuestions(): bool
    {
        return true;
    }

    /** 是否进入了可评价阶段（与"是否参与过"分开，便于给出不同的错误提示）。 */
    public function reviewOpen(Matter $matter): bool
    {
        return false;
    }

    /** 该成员是否参与过这件事（有接龙表态）。 */
    public function isParticipant(Matter $matter, Resident $resident): bool
    {
        return $matter->joins()->where('resident_id', $resident->id)->exists();
    }

    public function initialState(): string
    {
        return array_key_first($this->states());
    }

    protected function registrationOpen(Matter $matter): bool
    {
        return ! $matter->registrationHasClosed();
    }

    /**
     * 旁路终态的显示名（如 未成团/已取消）：半途收场的出口，从任意非终态可直接进入，
     * 不触发评价/联系互通等事后能力。null 表示该类型没有半途收场一说（如公告/征集）。
     */
    public function abortLabel(): ?string
    {
        return null;
    }

    public function hasAbort(): bool
    {
        return $this->abortLabel() !== null;
    }

    /**
     * 全部合法状态（顺序流转的主线 + 旁路终态），校验与显示用。
     *
     * @return array<string, string>
     */
    public function allStates(): array
    {
        $label = $this->abortLabel();

        return $label === null
            ? $this->states()
            : $this->states() + [self::ABORT_STATE => $label];
    }

    /**
     * 终态（状态机最后一个状态如已成团/已有结果，以及旁路终态）：进入后发起人不能再回退——
     * 联系方式互通、评价等事后能力都以终态为闸门，回退会撕裂数据语义。管理端不受限，作为纠错通道。
     */
    public function isFinalState(string $state): bool
    {
        return $state === self::ABORT_STATE || $state === array_key_last($this->states());
    }

    public function stateLabel(string $state): string
    {
        return $this->allStates()[$state] ?? $state;
    }

    /**
     * 发起人侧的合法状态流转：只能沿状态机顺序推进一步，或从任意非终态直接收场（旁路终态）。
     * 跳步会绕过中间环节（如一步跳到已成团直接触发联系互通），回退会撕裂事后数据语义，
     * 都不允许；确需纠错走管理端（不受此限）。
     */
    public function canAdvanceTo(string $from, string $to): bool
    {
        if ($this->isFinalState($from)) {
            return false;
        }

        if ($to === self::ABORT_STATE) {
            return $this->hasAbort();
        }

        $states = array_keys($this->states());
        $fromIndex = array_search($from, $states, true);

        return $fromIndex !== false && array_search($to, $states, true) === $fromIndex + 1;
    }

    /** 是否开放接龙表态。 */
    public function allowsJoin(Matter $matter): bool
    {
        return false;
    }

    /**
     * 此刻报名落下的承诺档位：null = 该类型不分档（报名即参与）。
     * 团购分 intent（登记意向）与 confirmed（确认参团）两档，见 GroupbuyType。
     */
    public function joinStage(Matter $matter): ?string
    {
        return null;
    }

    /**
     * 是否进入联系方式互通阶段（如团购成团后）：
     * 牵头人可见同意共享者的手机号，同意共享的参与者可见牵头人手机号。
     */
    public function contactsOpen(Matter $matter): bool
    {
        return false;
    }

    /** 互通阶段里这条接龙是否参与联系交换（如标品团购只跟确认参团的人互通）。 */
    public function contactEligible(Matter $matter, Stance $join): bool
    {
        return true;
    }

    /**
     * 发起时锁定、编辑不可再改的 payload 键（如团购的方案型开关：
     * 中途翻转会改变联系互通的隐私承诺）。管理端不受此限。
     *
     * @return array<int, string>
     */
    public function lockedPayloadKeys(): array
    {
        return [];
    }

    /** 接龙名单是否对外公示（否=对外只公示计数，明细仅牵头人可见，如维权联名）。 */
    public function rosterPublic(Matter $matter): bool
    {
        return true;
    }

    /** 列表排序权重（小者在前）。 */
    public function sortWeight(Matter $matter): int
    {
        return 50;
    }

    /**
     * 从请求数据里挑出属于 payload 的字段并规整默认值。
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function payloadFrom(array $validated): array
    {
        $payload = [];
        foreach (array_keys($this->payloadRules()) as $key) {
            if (! str_contains($key, '.')) {
                $payload[$key] = $validated[$key] ?? null;
            }
        }

        return $payload;
    }
}
