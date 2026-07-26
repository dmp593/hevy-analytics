<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkoutExercise extends Model
{
    protected $guarded = [];

    public function workout(): BelongsTo
    {
        return $this->belongsTo(Workout::class);
    }

    public function sets(): HasMany
    {
        return $this->hasMany(WorkoutSet::class)->orderBy('index');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ExerciseTemplate::class, 'exercise_template_hevy_id', 'hevy_id');
    }
}
