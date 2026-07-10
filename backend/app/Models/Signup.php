<?php

namespace App\Models;

use Database\Factories\SignupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $project_id
 * @property int $resident_id
 * @property-read Resident $resident
 */
class Signup extends Model
{
    /** @use HasFactory<SignupFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'resident_id',
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Resident, $this> */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }
}
