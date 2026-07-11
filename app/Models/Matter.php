<?php

namespace App\Models;

use App\Matters\MatterType;
use App\Matters\MatterTypeRegistry;
use Database\Factories\MatterFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 事项：社区正在处理的一件事（系统的中心运行时对象）。
 *
 * @property int $id
 * @property string $type
 * @property int|null $initiator_id
 * @property int|null $initiator_party_id
 * @property string $title
 * @property string $category
 * @property string $state
 * @property bool $is_approved
 * @property int $target_count
 * @property array<string, mixed>|null $payload
 * @property-read Resident|null $initiator
 * @property-read Party|null $initiatorParty
 */
class Matter extends Model
{
    /** @use HasFactory<MatterFactory> */
    use HasFactory;

    /** 管理员删除为软删除：误删可恢复，表态/评价/时间线一并保留。 */
    use SoftDeletes;

    protected $fillable = [
        'type',
        'initiator_id',
        'initiator_party_id',
        'title',
        'category',
        'state',
        'is_approved',
        'target_count',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'is_approved' => 'boolean',
            'payload' => 'array',
        ];
    }

    public function typeDef(): MatterType
    {
        return MatterTypeRegistry::for($this->type);
    }

    public function payloadValue(string $key, mixed $default = null): mixed
    {
        return $this->payload[$key] ?? $default;
    }

    /**
     * payload 中的列表字段（modules/terms 等），缺失或非法时按空列表处理。
     *
     * @return array<array-key, mixed>
     */
    public function payloadList(string $key): array
    {
        $value = $this->payloadValue($key);

        return is_array($value) ? $value : [];
    }

    /** @return BelongsTo<Resident, $this> */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(Resident::class, 'initiator_id');
    }

    /**
     * 发起时的相关方身份快照（已认证商家发起的事项带商家署名）。
     *
     * @return BelongsTo<Party, $this>
     */
    public function initiatorParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'initiator_party_id');
    }

    /** @return HasMany<Stance, $this> */
    public function stances(): HasMany
    {
        return $this->hasMany(Stance::class);
    }

    /**
     * 接龙表态。
     *
     * @return HasMany<Stance, $this>
     */
    public function joins(): HasMany
    {
        return $this->stances()->where('mode', Stance::MODE_JOIN);
    }

    /**
     * 评价表态。
     *
     * @return HasMany<Stance, $this>
     */
    public function reviews(): HasMany
    {
        return $this->stances()->where('mode', Stance::MODE_REVIEW);
    }

    /** @return HasMany<MatterUpdate, $this> */
    public function updates(): HasMany
    {
        return $this->hasMany(MatterUpdate::class);
    }

    /**
     * @param  Builder<Matter>  $query
     * @return Builder<Matter>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('is_approved', true);
    }
}
