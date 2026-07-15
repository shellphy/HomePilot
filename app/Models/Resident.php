<?php

namespace App\Models;

use Database\Factories\ResidentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * 成员：人通过与户的关系存在于社区中。
 * unionid 是人本身，openid_mp 是他在小程序这一端的投影。
 *
 * @property int $id
 * @property string $unionid
 * @property string $openid_mp
 * @property string $nickname
 * @property string $avatar
 * @property string $phone
 * @property string $unit_label
 * @property string $room_label
 * @property int|null $affiliated_party_id
 * @property int|null $last_party_id
 * @property bool $is_admin
 * @property bool $is_super_admin
 * @property int|null $admin_granted_by_id
 * @property Carbon|null $admin_granted_at
 * @property Carbon|null $blocked_at
 * @property int|null $blocked_by_id
 * @property Carbon|null $mine_seen_at
 * @property Carbon|null $joined_seen_at
 * @property-read Party|null $affiliatedParty
 * @property-read Party|null $lastParty
 * @property-read Resident|null $adminGrantedBy
 * @property-read Resident|null $blockedBy
 */
class Resident extends Authenticatable
{
    /** @use HasFactory<ResidentFactory> */
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'unionid',
        'openid_mp',
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
            'is_super_admin' => 'boolean',
            'admin_granted_at' => 'datetime',
            'blocked_at' => 'datetime',
            'mine_seen_at' => 'datetime',
            'joined_seen_at' => 'datetime',
        ];
    }

    /**
     * 授权我的超级管理员（CLI 种下的创始人为 null）。
     *
     * @return BelongsTo<Resident, $this>
     */
    public function adminGrantedBy(): BelongsTo
    {
        return $this->belongsTo(Resident::class, 'admin_granted_by_id');
    }

    /** 设为管理员并记下是谁、何时授权（审计）。 */
    public function grantAdmin(Resident $by): void
    {
        $this->forceFill([
            'is_admin' => true,
            'admin_granted_by_id' => $by->id,
            'admin_granted_at' => now(),
        ])->save();
    }

    /**
     * 拉黑我的管理员（unblock 后清空）。
     *
     * @return BelongsTo<Resident, $this>
     */
    public function blockedBy(): BelongsTo
    {
        return $this->belongsTo(Resident::class, 'blocked_by_id');
    }

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    /** 拉黑：限制参与社区互动，记下是谁、何时拉黑。 */
    public function block(Resident $by): void
    {
        $this->forceFill([
            'blocked_at' => now(),
            'blocked_by_id' => $by->id,
        ])->save();
    }

    public function unblock(): void
    {
        $this->forceFill([
            'blocked_at' => null,
            'blocked_by_id' => null,
        ])->save();
    }

    /** 收回管理员，清掉授权记录（超级管理员身份不受影响）。 */
    public function revokeAdmin(): void
    {
        $this->forceFill([
            'is_admin' => false,
            'admin_granted_by_id' => null,
            'admin_granted_at' => null,
        ])->save();
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
     * 我牵头或参与、且有我没看过的新动态的事项（喂给「待我处理」）。
     *
     * @return EloquentCollection<int, Matter>
     */
    public function unseenActivityMatters(): EloquentCollection
    {
        $mine = $this->unseenActivityQuery()->whereBelongsTo($this, 'initiator')->get();
        $joined = $this->unseenActivityQuery()
            ->approved()
            ->whereHas('joins', fn ($query) => $query->whereBelongsTo($this, 'resident'))
            ->get();

        return $mine->merge($joined);
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
