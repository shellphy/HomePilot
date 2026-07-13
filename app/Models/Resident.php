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
        'layout_label',
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
        return $this->unseenActivityQuery()
            ->whereBelongsTo($this, 'initiator')
            ->exists();
    }

    /**
     * 「我参与的」有没有我没看过的新动态（状态流转、成交公示、进展等，排除我自己触发的）。
     */
    public function hasJoinedUpdates(): bool
    {
        return $this->unseenActivityQuery()
            ->approved()
            ->whereHas('joins', fn ($query) => $query->whereBelongsTo($this, 'resident'))
            ->exists();
    }

    /**
     * 有没有进行中、我还没参与过的征集（喂给「数据」tab 红点）。
     */
    public function hasUnansweredCensus(): bool
    {
        return Matter::approved()
            ->where('type', 'census')
            ->where('state', 'open')
            ->whereDoesntHave('stances', fn ($query) => $query
                ->where('mode', Stance::MODE_REGISTER)
                ->where('resident_id', $this->id))
            ->exists();
    }

    /**
     * @return Builder<Matter>
     */
    private function unseenActivityQuery(): Builder
    {
        return Matter::query()
            ->whereNotNull('last_activity_at')
            ->where(fn ($query) => $query
                ->whereNull('last_activity_resident_id')
                ->orWhere('last_activity_resident_id', '!=', $this->id))
            ->where(fn ($query) => $query
                ->whereDoesntHave('reads', fn ($read) => $read->where('resident_id', $this->id))
                ->orWhereHas('reads', fn ($read) => $read
                    ->where('resident_id', $this->id)
                    ->whereColumn('matter_reads.seen_at', '<', 'matters.last_activity_at')));
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

    /** @return HasMany<MatterRead, $this> */
    public function matterReads(): HasMany
    {
        return $this->hasMany(MatterRead::class);
    }

    /**
     * 接龙名单里的对外展示名，如 "3栋 老K"。
     */
    public function displayName(): string
    {
        return trim($this->unit_label.' '.$this->nickname);
    }
}
