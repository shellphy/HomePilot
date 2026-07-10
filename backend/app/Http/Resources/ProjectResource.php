<?php

namespace App\Http\Resources;

use App\Models\Project;
use App\Models\Signup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Project
 */
class ProjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'initiator_id' => $this->initiator_id,
            'initiator_name' => $this->whenLoaded('initiator', fn () => $this->initiator?->displayName()),
            'category' => $this->category,
            'title' => $this->title,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_approved' => $this->is_approved,
            'target_households' => $this->target_households,
            'signups_count' => $this->whenCounted('signups'),
            'perk' => $this->perk,
            'pitch' => $this->pitch,
            'terms' => $this->terms ?? [],
            'glossary' => $this->glossary ?? [],
            'roster' => $this->whenLoaded(
                'signups',
                fn () => $this->signups->map(fn (Signup $signup): string => $signup->resident->displayName()),
            ),
            'progress_updates' => ProgressUpdateResource::collection($this->whenLoaded('progressUpdates')),
        ];
    }
}
