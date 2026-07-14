<?php

namespace App\Http\Resources;

use App\Matters\MatterType;
use App\Matters\MatterTypeRegistry;
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
     * 正文走 body 列；payload 里的类型专属字段（groupbuy 的 perk/terms/glossary/relationship/rebate_note/final_terms/final_note）
     * 平铺为顶层字段——小程序端不感知 payload 这个实现细节。
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = MatterTypeRegistry::for($this->type);

        // 管理员或发起人本人可看编辑用的原始 payload；用 instanceof 收窄到 Resident（auth 联合类型里 User 无 is_admin）
        $user = $request->user();
        $canEdit = ($user instanceof Resident && $user->is_admin)
            || ($user !== null && $this->initiator_id === $user->id);

        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_label' => $type->label(),
            'initiator_id' => $this->initiator_id,
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
            'abort_state' => $type->hasAbort()
                ? ['value' => MatterType::ABORT_STATE, 'label' => $type->abortLabel()]
                : null,
            'is_approved' => $this->is_approved,
            'review_status' => $this->review_status->value,
            'review_status_label' => $this->review_status->label(),
            'target_count' => $this->target_count,
            'join_open' => $type->allowsJoin($this->resource),
            'review_open' => $type->reviewOpen($this->resource),
            'contacts_open' => $type->contactsOpen($this->resource),
            'join_count' => $this->whenCounted('joins'),
            'confirmed_count' => $this->whenCounted('confirmedJoins'),
            'needs_survey' => (bool) $this->payloadValue('needs_survey', false),
            'register_count' => (int) ($this->register_count ?? 0),
            'registered_by_me' => (bool) ($this->registered_by_me ?? false),
            'body' => $this->body,
            'perk' => $this->payloadValue('perk', ''),
            'terms' => $this->payloadValue('terms', []),
            'relationship' => $this->payloadValue('relationship', ''),
            'relationship_label' => $this->relationshipLabel(),
            'rebate_note' => $this->payloadValue('rebate_note', ''),
            'glossary' => $this->payloadValue('glossary', []),
            'final_terms' => $this->payloadValue('final_terms', []),
            'final_note' => $this->payloadValue('final_note', ''),
            'published_on' => $this->created_at?->format('m-d'),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'starts_on' => $this->starts_at?->format('m-d H:i'),
            'registration_deadline_at' => $this->registration_deadline_at?->toIso8601String(),
            'registration_deadline_on' => $this->registration_deadline_at?->format('m-d H:i'),
            'registration_closed' => $this->registrationHasClosed(),
            'location' => $this->location,
            'has_unread_updates' => $user instanceof Resident && $this->last_activity_at !== null
                ? $this->hasUnreadActivityFor($user)
                : false,
            'reject_reason' => $this->reject_reason,
            'roster_hidden' => ! $type->rosterPublic($this->resource),
            'roster' => $this->whenLoaded(
                'joins',
                fn () => $type->rosterPublic($this->resource) || $this->initiator_id === $request->user()?->id
                    ? $this->joins->map(fn (Stance $join): string => $join->resident->displayName())
                    : collect(),
            ),
            'updates' => MatterUpdateResource::collection($this->whenLoaded('updates')),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
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

    /** 团购利益关系披露的展示文案（非团购或未填为空）。 */
    private function relationshipLabel(): string
    {
        return match ($this->payloadValue('relationship')) {
            'merchant_direct' => '商家直供，本团由商家自己发起',
            'none' => '发起人与商家无利益关系，纯热心张罗',
            'rebate' => '发起人会从中获得返点或好处',
            'affiliated' => '发起人关联这家商家',
            default => '',
        };
    }
}
