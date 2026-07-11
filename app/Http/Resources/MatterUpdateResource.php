<?php

namespace App\Http\Resources;

use App\Models\MatterUpdate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MatterUpdate
 */
class MatterUpdateResource extends JsonResource
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
            // 官方回应的署名（如 "物业 · 天青府物业服务中心"）；牵头人进展为 null
            'author' => $this->authorParty ? $this->authorParty->typeLabel().' · '.$this->authorParty->name : null,
        ];
    }
}
