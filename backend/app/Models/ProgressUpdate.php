<?php

namespace App\Models;

use Database\Factories\ProgressUpdateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property Carbon $happened_on
 * @property string $content
 * @property array<int, string>|null $images
 */
class ProgressUpdate extends Model
{
    /** @use HasFactory<ProgressUpdateFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
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

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
