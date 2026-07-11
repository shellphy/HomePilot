<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 表态的历史版本（append-only）。
 *
 * @property int $id
 * @property int $stance_id
 * @property array<string, mixed> $payload
 */
class StanceRevision extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['stance_id', 'payload'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    /** @return BelongsTo<Stance, $this> */
    public function stance(): BelongsTo
    {
        return $this->belongsTo(Stance::class);
    }
}
