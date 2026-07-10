<?php

namespace App\Models;

use Database\Factories\ResidentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $openid
 * @property string $nickname
 * @property string $avatar
 * @property string $unit_label
 * @property string $phone
 * @property string $wechat_id
 * @property string $role
 * @property string $merchant_name
 * @property string $merchant_category
 */
class Resident extends Authenticatable
{
    /** @use HasFactory<ResidentFactory> */
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'openid',
        'nickname',
        'avatar',
        'unit_label',
        'phone',
        'wechat_id',
        'role',
        'merchant_name',
        'merchant_category',
    ];

    public function isMerchant(): bool
    {
        return $this->role === 'merchant';
    }

    /** @return HasOne<Registration, $this> */
    public function registration(): HasOne
    {
        return $this->hasOne(Registration::class);
    }

    /** @return HasMany<Signup, $this> */
    public function signups(): HasMany
    {
        return $this->hasMany(Signup::class);
    }

    /**
     * 接龙名单里的对外展示名，如 "3-2-1801 老K"。
     */
    public function displayName(): string
    {
        return trim($this->unit_label.' '.$this->nickname);
    }
}
