<?php

namespace App\Models;

use Database\Factories\ResidentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
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
        ];
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
