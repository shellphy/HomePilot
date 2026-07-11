<?php

namespace App\Matters;

use App\Models\Matter;
use App\Models\Resident;

/**
 * 事项类型：定义一类社区事项装载哪些能力——状态机、payload 校验、
 * 表态模式开关、列表排序权重。新场景 = 新增一个子类，不新建表、不新建端点。
 */
abstract class MatterType
{
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

    /**
     * 终态（状态机最后一个状态，如已成团/已有结果）：进入后发起人不能再回退——
     * 联系方式互通、评价等事后能力都以终态为闸门，回退会撕裂数据语义。管理端不受限，作为纠错通道。
     */
    public function isFinalState(string $state): bool
    {
        return $state === array_key_last($this->states());
    }

    public function stateLabel(string $state): string
    {
        return $this->states()[$state] ?? $state;
    }

    /** 是否开放接龙表态。 */
    public function allowsJoin(Matter $matter): bool
    {
        return false;
    }

    /**
     * 是否进入联系方式互通阶段（如团购成团后）：
     * 牵头人可见同意共享者的手机号，同意共享的参与者可见牵头人手机号。
     */
    public function contactsOpen(Matter $matter): bool
    {
        return false;
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
