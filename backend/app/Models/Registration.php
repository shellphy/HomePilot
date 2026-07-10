<?php

namespace App\Models;

use Database\Factories\RegistrationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $resident_id
 * @property string $layout
 * @property string $decoration_mode
 * @property array<int, string> $interests
 * @property array<string, string|array<int, string>>|null $answers
 */
class Registration extends Model
{
    /** @use HasFactory<RegistrationFactory> */
    use HasFactory;

    protected $fillable = [
        'resident_id',
        'layout',
        'decoration_mode',
        'interests',
        'answers',
    ];

    protected function casts(): array
    {
        return [
            'interests' => 'array',
            'answers' => 'array',
        ];
    }

    /** @return BelongsTo<Resident, $this> */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }
}
