<?php

namespace App\Http\Resources;

use App\Matters\MatterType;
use App\Models\Matter;
use App\Models\Resident;
use App\Models\Stance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Matter
 */
class MatterResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * payload 按类型平铺为顶层字段：groupbuy 的 pitch/perk/terms/glossary/final_terms/final_note、
     * notice 的 body——小程序端不感知 payload 这个实现细节。
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = $this->typeDef();

        // 管理员或发起人本人可看编辑用的原始 payload；用 instanceof 收窄到 Resident（auth 联合类型里 User 无 is_admin）
        $user = $request->user();
        $canEdit = ($user instanceof Resident && $user->is_admin)
            || ($user !== null && $this->initiator_id === $user->id);

        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_label' => $type->label(),
            'initiator_id' => $this->initiator_id,
            // 商家发起的事项署商家名（发起时的身份快照），业主发起的署「楼栋 + 昵称」
            'initiator_name' => $this->whenLoaded('initiator', fn () => $this->initiatorParty ? $this->initiatorParty->name : $this->initiator?->displayName()),
            'initiator_party' => $this->whenLoaded('initiatorParty', fn () => $this->initiatorParty ? [
                'label' => $this->initiatorParty->typeLabel(),
                'name' => $this->initiatorParty->name,
                'is_listed' => $this->initiatorParty->is_listed,
            ] : null),
            'category' => $this->category,
            'title' => $this->title,
            'state' => $this->state,
            'state_label' => $type->stateLabel($this->state),
            'states' => $type->states(),
            // 旁路终态（未成团/已取消）：发起人从任意非终态可直接收场，与顺序推进分开渲染
            'abort_state' => $type->hasAbort()
                ? ['value' => MatterType::ABORT_STATE, 'label' => $type->abortLabel()]
                : null,
            'is_approved' => $this->is_approved,
            'review_status' => $this->review_status->value,
            'review_status_label' => $this->review_status->label(),
            'target_count' => $this->target_count,
            // 表态阶段开关（由类型状态机决定），小程序端据此渲染报名/评价区，不自行推断状态
            'join_open' => $type->allowsJoin($this->resource),
            'review_open' => $type->reviewOpen($this->resource),
            'contacts_open' => $type->contactsOpen($this->resource),
            'join_count' => $this->whenCounted('joins'),
            // 确认参团数（团购两段表态里进成交名单的口径；其余类型与 join_count 一致）
            'confirmed_count' => $this->whenCounted('confirmedJoins'),
            // 方案型团购（如中央空调）：商家逐户沟通需求、单独出方案，联系互通从谈判中开始
            'needs_survey' => (bool) $this->payloadValue('needs_survey', false),
            'register_count' => (int) ($this->register_count ?? 0),
            'registered_by_me' => (bool) ($this->registered_by_me ?? false),
            'pitch' => $this->payloadValue('pitch', ''),
            'perk' => $this->payloadValue('perk', ''),
            'terms' => $this->payloadValue('terms', []),
            'glossary' => $this->payloadValue('glossary', []),
            'final_terms' => $this->payloadValue('final_terms', []),
            'final_note' => $this->payloadValue('final_note', ''),
            'body' => $this->payloadValue('body', ''),
            'published_on' => $this->created_at?->format('m-d'),
            // 被驳回时给发起人看的理由（未审核的事项只有发起人能打开详情）
            'reject_reason' => $this->reject_reason,
            // 名单不对外公示的类型（如维权联名）：对外只给计数，明细仅牵头人可见
            'roster_hidden' => ! $type->rosterPublic($this->resource),
            'roster' => $this->whenLoaded(
                'joins',
                fn () => $type->rosterPublic($this->resource) || $this->initiator_id === $request->user()?->id
                    ? $this->joins->map(fn (Stance $join): string => $join->resident->displayName())
                    : collect(),
            ),
            'updates' => MatterUpdateResource::collection($this->whenLoaded('updates')),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
            // 编辑表单要用的原始 payload（含 census 的 modules/purpose）+ 全部状态 + 署名/创建时间：
            // 管理员或发起人本人可见（业主/商家发起 census 后要能读回自己的问卷去编辑）
            $this->mergeWhen(
                $canEdit,
                fn (): array => [
                    'payload' => $this->payload ?? (object) [],
                    'all_states' => $type->allStates(),
                    'initiator_party_id' => $this->initiator_party_id,
                    'created_at' => $this->created_at?->format('Y-m-d H:i'),
                ]
            ),
        ];
    }
}
