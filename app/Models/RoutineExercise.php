<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutineExercise extends Model
{
    protected $guarded = [];

    protected $casts = [
        'sets' => 'array',
    ];

    public function routine(): BelongsTo
    {
        return $this->belongsTo(Routine::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ExerciseTemplate::class, 'exercise_template_hevy_id', 'hevy_id');
    }
}
