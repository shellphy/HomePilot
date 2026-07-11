<?php

namespace App\Http\Resources;

use App\Models\Resident;
use App\Models\Stance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Resident
 */
class ResidentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * party 为 null 表示业主身份；有值即以相关方身份出现（商家/物业等）。
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
            'room_label' => $this->room_label,
            'phone' => $this->phone,
            'is_admin' => $this->is_admin,
            'party' => $this->affiliatedParty ? [
                'type' => $this->affiliatedParty->type,
                'label' => $this->affiliatedParty->typeLabel(),
                'name' => $this->affiliatedParty->name,
                'category' => $this->affiliatedParty->category,
                'is_listed' => $this->affiliatedParty->is_listed,
            ] : null,
            // 上次绑定的相关方档案：切回业主后再进资料页，商家资料按它预填
            'last_party' => (! $this->affiliatedParty && $this->lastParty) ? [
                'type' => $this->lastParty->type,
                'name' => $this->lastParty->name,
                'category' => $this->lastParty->category,
            ] : null,
            // 我参与过的征集（通用：装修摸底、将来的收房/车位摸底都在这里）
            'censuses' => $this->stances()
                ->where('mode', Stance::MODE_REGISTER)
                ->whereHas('matter', fn ($query) => $query->where('type', 'census'))
                ->with('matter')
                ->get()
                ->map(fn (Stance $stance): array => [
                    'matter_id' => $stance->matter_id,
                    'title' => $stance->matter->title,
                    'answered' => count($stance->payload['answers'] ?? []),
                ])
                ->values(),
        ];
    }
}
