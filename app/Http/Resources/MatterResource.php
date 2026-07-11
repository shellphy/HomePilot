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
            'initiator_name' => $this->whenLoaded('initiator', fn () => $this->initiator?->displayName()),
            'category' => $this->category,
            'title' => $this->title,
            'state' => $this->state,
            'state_label' => $type->stateLabel($this->state),
            'states' => $type->states(),
            'is_approved' => $this->is_approved,
            'target_count' => $this->target_count,
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
            'roster' => $this->whenLoaded(
                'joins',
                fn () => $this->joins->map(fn (Stance $join): string => $join->resident->displayName()),
            ),
            'updates' => MatterUpdateResource::collection($this->whenLoaded('updates')),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
        ];
    }
}
