<?php

namespace App\Http\Resources;

use App\Models\Record;
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
            'unit_label' => $this->unit?->label ?? '',
            'room_label' => $this->room_label,
            'phone' => $this->phone,
            'wechat_id' => $this->wechat_id,
            'party' => $this->party ? [
                'type' => $this->party->type,
                'label' => $this->party->typeLabel(),
                'name' => $this->party->name,
                'category' => $this->party->category,
                'is_listed' => $this->party->is_listed,
            ] : null,
            // 我参与过的征集（通用：装修摸底、将来的收房/车位摸底都在这里）
            'censuses' => $this->records()
                ->where('mode', Record::MODE_REGISTER)
                ->whereHas('matter', fn ($query) => $query->where('type', 'census'))
                ->with('matter')
                ->get()
                ->map(fn (Record $record): array => [
                    'matter_id' => $record->matter_id,
                    'title' => $record->matter?->title ?? '',
                    'answered' => count($record->payload['answers'] ?? []),
                ])
                ->values(),
        ];
    }
}
