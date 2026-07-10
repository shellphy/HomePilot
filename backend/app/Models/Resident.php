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
 * @property string $wechat_id
 * @property string $phone
 * @property string $room_label
 * @property int|null $unit_id
 * @property int|null $party_id
 * @property bool $is_admin
 * @property-read Unit|null $unit
 * @property-read Party|null $party
 */
class Resident extends Authenticatable
{
    /** @use HasFactory<ResidentFactory> */
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'openid',
        'nickname',
        'avatar',
        'wechat_id',
        'phone',
        'room_label',
        'unit_id',
        'party_id',
    ];

    protected function casts(): array
    {
        return [
            'is_admin' => 'boolean',
        ];
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsTo<Party, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /** @return HasMany<Record, $this> */
    public function records(): HasMany
    {
        return $this->hasMany(Record::class);
    }

    /**
     * 把楼栋号绑定为户对象（不存在则创建）。
     */
    public function bindUnit(string $label): void
    {
        $this->update(['unit_id' => Unit::firstOrCreate(['label' => trim($label)])->id]);
    }

    /**
     * 接龙名单里的对外展示名，如 "3栋 老K"。
     */
    public function displayName(): string
    {
        return trim(($this->unit?->label ?? '').' '.$this->nickname);
    }
}
