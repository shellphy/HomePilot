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

/**
 * 事务：社区正在处理的一件事（系统的中心运行时对象）。
 *
 * @property int $id
 * @property string $type
 * @property int|null $initiator_id
 * @property int|null $party_id
 * @property string $title
 * @property string $category
 * @property string $state
 * @property bool $is_approved
 * @property int $target_count
 * @property array<string, mixed>|null $payload
 */
class Matter extends Model
{
    /** @use HasFactory<MatterFactory> */
    use HasFactory;

    protected $fillable = [
        'type',
        'initiator_id',
        'party_id',
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

    /** @return BelongsTo<Resident, $this> */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(Resident::class, 'initiator_id');
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

    /** 接龙表态。@return HasMany<Record, $this> */
    public function joins(): HasMany
    {
        return $this->records()->where('mode', Record::MODE_JOIN);
    }

    /** 评价表态。@return HasMany<Record, $this> */
    public function reviews(): HasMany
    {
        return $this->records()->where('mode', Record::MODE_REVIEW);
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
