<?php

namespace App\Http\Resources;

use App\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Registration
 */
class RegistrationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'layout' => $this->layout,
            'decoration_mode' => $this->decoration_mode,
            'interests' => $this->interests,
            'answered' => count($this->answers ?? []),
            'updated_at' => $this->updated_at,
        ];
    }
}
