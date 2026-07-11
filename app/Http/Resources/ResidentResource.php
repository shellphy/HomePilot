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
            // 「我的」页红点：我牵头的/我参与的有没有我没看过的新动态（POST /me/seen 标记已读）
            'has_mine_updates' => $this->hasMineUpdates(),
            'has_joined_updates' => $this->hasJoinedUpdates(),
            'party' => $this->affiliatedParty ? [
                'id' => $this->affiliatedParty->id,
                'type' => $this->affiliatedParty->type,
                'label' => $this->affiliatedParty->typeLabel(),
                'name' => $this->affiliatedParty->name,
                'category' => $this->affiliatedParty->category,
                'intro' => $this->affiliatedParty->intro,
                'description' => $this->affiliatedParty->description ?? '',
                'images' => $this->affiliatedParty->images ?? [],
                'is_listed' => $this->affiliatedParty->is_listed,
            ] : null,
            // 上次绑定的相关方档案：切回业主后再进资料页，商家资料按它预填
            'last_party' => (! $this->affiliatedParty && $this->lastParty) ? [
                'type' => $this->lastParty->type,
                'name' => $this->lastParty->name,
                'category' => $this->lastParty->category,
                'intro' => $this->lastParty->intro,
                'description' => $this->lastParty->description ?? '',
                'images' => $this->lastParty->images ?? [],
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
