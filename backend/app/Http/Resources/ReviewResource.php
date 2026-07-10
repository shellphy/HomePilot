<?php

namespace App\Http\Resources;

use App\Models\Record;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 评价表态（mode=review 的记录）。
 *
 * @mixin Record
 */
class ReviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'reviewer_name' => $this->whenLoaded('resident', fn () => $this->resident->displayName()),
            'rating' => (int) ($this->payload['rating'] ?? 0),
            'content' => $this->payload['content'] ?? '',
            'reviewed_on' => $this->updated_at?->format('m-d'),
        ];
    }
}
