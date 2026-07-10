<?php

namespace App\Http\Resources;

use App\Models\ProgressUpdate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProgressUpdate
 */
class ProgressUpdateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'happened_on' => $this->happened_on->format('m-d'),
            'content' => $this->content,
            'images' => $this->images ?? [],
        ];
    }
}
