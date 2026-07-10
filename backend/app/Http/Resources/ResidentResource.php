<?php

namespace App\Http\Resources;

use App\Models\Resident;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Resident
 */
class ResidentResource extends JsonResource
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
            'nickname' => $this->nickname,
            'avatar' => $this->avatar,
            'unit_label' => $this->unit_label,
            'phone' => $this->phone,
            'wechat_id' => $this->wechat_id,
            'role' => $this->role,
            'merchant_name' => $this->merchant_name,
            'merchant_category' => $this->merchant_category,
            'registration' => RegistrationResource::make($this->whenLoaded('registration')),
        ];
    }
}
