<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 记录的历史版本（append-only）。
 *
 * @property int $id
 * @property int $record_id
 * @property array<string, mixed> $payload
 */
class RecordRevision extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['record_id', 'payload'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    /** @return BelongsTo<Record, $this> */
    public function record(): BelongsTo
    {
        return $this->belongsTo(Record::class);
    }
}
