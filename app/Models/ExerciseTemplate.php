<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExerciseTemplate extends Model
{
    protected $guarded = [];

    protected $casts = [
        'secondary_muscle_groups' => 'array',
        'is_custom' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
