<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $initiator_id
 * @property string $category
 * @property string $title
 * @property ProjectStatus $status
 * @property bool $is_approved
 * @property int $target_households
 * @property string|null $pitch
 * @property array<int, array{label: string, value: string}>|null $terms
 * @property string $perk
 * @property array<int, array{term: string, explain: string}>|null $glossary
 */
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    protected $fillable = [
        'initiator_id',
        'category',
        'title',
        'status',
        'is_approved',
        'target_households',
        'pitch',
        'terms',
        'perk',
        'glossary',
    ];

    protected $attributes = [
        'status' => 'seeking',
        'target_households' => 0,
        'perk' => '',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'is_approved' => 'boolean',
            'terms' => 'array',
            'glossary' => 'array',
        ];
    }

    /** @return BelongsTo<Resident, $this> */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(Resident::class, 'initiator_id');
    }

    /**
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('is_approved', true);
    }

    /** @return HasMany<Signup, $this> */
    public function signups(): HasMany
    {
        return $this->hasMany(Signup::class);
    }

    /** @return HasMany<ProgressUpdate, $this> */
    public function progressUpdates(): HasMany
    {
        return $this->hasMany(ProgressUpdate::class);
    }
}
