<?php

namespace App\Models;

use Database\Factories\ResidentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * 成员：人通过与户的关系存在于社区中。
 *
 * @property int $id
 * @property string $openid
 * @property string $nickname
 * @property string $avatar
 * @property string $phone
 * @property string $unit_label
 * @property string $room_label
 * @property int|null $affiliated_party_id
 * @property int|null $last_party_id
 * @property bool $is_admin
 * @property Carbon|null $mine_seen_at
 * @property Carbon|null $joined_seen_at
 * @property-read Party|null $affiliatedParty
 * @property-read Party|null $lastParty
 */
class Resident extends Authenticatable
{
    /** @use HasFactory<ResidentFactory> */
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'openid',
        'nickname',
        'avatar',
        'phone',
        'unit_label',
        'room_label',
        'affiliated_party_id',
        'last_party_id',
    ];

    protected function casts(): array
    {
        return [
            'is_admin' => 'boolean',
            'mine_seen_at' => 'datetime',
            'joined_seen_at' => 'datetime',
        ];
    }

    /**
     * 「我牵头的」有没有我没看过的新动态（审核结果、官方回应等，排除我自己触发的）。
     */
    public function hasMineUpdates(): bool
    {
        return $this->unseenActivityQuery($this->mine_seen_at)
            ->whereBelongsTo($this, 'initiator')
            ->exists();
    }

    /**
     * 「我参与的」有没有我没看过的新动态（状态流转、成交公示、进展等，排除我自己触发的）。
     */
    public function hasJoinedUpdates(): bool
    {
        return $this->unseenActivityQuery($this->joined_seen_at)
            ->approved()
            ->whereHas('joins', fn ($query) => $query->whereBelongsTo($this, 'resident'))
            ->exists();
    }

    /**
     * @param  Carbon|null  $seenAt
     * @return Builder<Matter>
     */
    private function unseenActivityQuery($seenAt)
    {
        return Matter::query()
            ->whereNotNull('last_activity_at')
            ->when($seenAt, fn ($query) => $query->where('last_activity_at', '>', $seenAt))
            ->where(fn ($query) => $query
                ->whereNull('last_activity_resident_id')
                ->orWhere('last_activity_resident_id', '!=', $this->id));
    }

    /**
     * 以哪个相关方身份出现（如商家入驻绑定 merchant 相关方）；业主为 null。
     *
     * @return BelongsTo<Party, $this>
     */
    public function affiliatedParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'affiliated_party_id');
    }

    /**
     * 上一次绑定的相关方（切回业主后仍记着），再次入驻时找回原档案。
     *
     * @return BelongsTo<Party, $this>
     */
    public function lastParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'last_party_id');
    }

    /** @return HasMany<Stance, $this> */
    public function stances(): HasMany
    {
        return $this->hasMany(Stance::class);
    }

    /**
     * 接龙名单里的对外展示名，如 "3栋 老K"。
     */
    public function displayName(): string
    {
        return trim($this->unit_label.' '.$this->nickname);
    }
}
