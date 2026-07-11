<?php

namespace App\Http\Resources;

use App\Models\Matter;
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
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = $this->typeDef();

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
            'is_approved' => $this->is_approved,
            'target_count' => $this->target_count,
            // 表态阶段开关（由类型状态机决定），小程序端据此渲染报名/评价区，不自行推断状态
            'join_open' => $type->allowsJoin($this->resource),
            'review_open' => $type->reviewOpen($this->resource),
            'join_count' => $this->whenCounted('joins'),
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
            'reject_reason' => $this->payloadValue('reject_reason', ''),
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
        ];
    }
}
