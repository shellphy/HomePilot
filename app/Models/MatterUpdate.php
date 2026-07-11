<?php

namespace App\Models;

use Database\Factories\MatterUpdateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * 事项时间线条目。author_party_id 非空 = 治理类相关方的官方回应。
 *
 * @property int $id
 * @property int $matter_id
 * @property int|null $author_party_id
 * @property Carbon $happened_on
 * @property string $content
 * @property array<int, string>|null $images
 * @property-read Party|null $authorParty
 */
class MatterUpdate extends Model
{
    /** @use HasFactory<MatterUpdateFactory> */
    use HasFactory;

    protected $fillable = [
        'matter_id',
        'author_party_id',
        'happened_on',
        'content',
        'images',
    ];

    protected function casts(): array
    {
        return [
            'happened_on' => 'date',
            'images' => 'array',
        ];
    }

    /** @return BelongsTo<Matter, $this> */
    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }

    /** @return BelongsTo<Party, $this> */
    public function authorParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'author_party_id');
    }
}
