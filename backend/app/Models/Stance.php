<?php

namespace App\Models;

use Database\Factories\StanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 表态：结构化表态的沉淀原子。
 * 修改表态请走 reviseTo()，旧值进修订链——"只增不改"。
 *
 * @property int $id
 * @property int $matter_id
 * @property int $resident_id
 * @property string $mode
 * @property array<string, mixed>|null $payload
 * @property-read Resident $resident
 */
class Stance extends Model
{
    /** @use HasFactory<StanceFactory> */
    use HasFactory;

    public const MODE_REGISTER = 'register';

    public const MODE_JOIN = 'join';

    public const MODE_REVIEW = 'review';

    protected $fillable = [
        'matter_id',
        'resident_id',
        'mode',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    /**
     * 修改表态：旧 payload 存入修订链后再覆盖。
     *
     * @param  array<string, mixed>  $payload
     */
    public function reviseTo(array $payload): void
    {
        $this->revisions()->create(['payload' => $this->payload ?? []]);
        $this->update(['payload' => $payload]);
    }

    /** @return BelongsTo<Matter, $this> */
    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }

    /** @return BelongsTo<Resident, $this> */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    /** @return HasMany<StanceRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(StanceRevision::class);
    }
}
