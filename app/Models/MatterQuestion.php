<?php

namespace App\Models;

use Database\Factories\MatterQuestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * 「大家都在问」：业主对某个事项的公开提问，由负责方（团长/商家/管理员）回答。
 * 只有一问一答两种内容，业主之间不互相回复；同问（echoes）聚合重复疑问的热度。
 *
 * @property int $id
 * @property int $matter_id
 * @property int $resident_id
 * @property string $content
 * @property string|null $answer
 * @property string $answered_by
 * @property Carbon|null $answered_at
 * @property-read Matter|null $matter 所属事项被软删后为 null
 * @property-read Resident $asker
 */
class MatterQuestion extends Model
{
    /** @use HasFactory<MatterQuestionFactory> */
    use HasFactory;

    protected $fillable = [
        'matter_id',
        'resident_id',
        'content',
        'answer',
        'answered_by',
        'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'answered_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Matter, $this> */
    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }

    /** @return BelongsTo<Resident, $this> */
    public function asker(): BelongsTo
    {
        return $this->belongsTo(Resident::class, 'resident_id');
    }

    /**
     * 同问的业主（每人一次）。
     *
     * @return BelongsToMany<Resident, $this>
     */
    public function echoers(): BelongsToMany
    {
        return $this->belongsToMany(Resident::class, 'matter_question_echoes')->withTimestamps();
    }
}
