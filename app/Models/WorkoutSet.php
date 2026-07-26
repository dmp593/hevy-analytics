<?php

namespace App\Models;

use App\Enums\SetType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkoutSet extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'weight_kg' => 'float',
        'reps' => 'float',
        'distance_meters' => 'float',
        'duration_seconds' => 'float',
        'rpe' => 'float',
        'custom_metric' => 'float',
    ];

    public function workoutExercise(): BelongsTo
    {
        return $this->belongsTo(WorkoutExercise::class);
    }

    public function isWorking(): bool
    {
        return SetType::fromRaw($this->type)->isWorking();
    }

    public function volume(): float
    {
        return (float) ($this->weight_kg ?? 0) * (float) ($this->reps ?? 0);
    }
}
