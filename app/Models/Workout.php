<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workout extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'hevy_created_at' => 'datetime',
        'hevy_updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(WorkoutExercise::class)->orderBy('index');
    }

    public function routine(): BelongsTo
    {
        return $this->belongsTo(Routine::class, 'routine_hevy_id', 'hevy_id');
    }

    public function durationMinutes(): ?float
    {
        if (! $this->start_time || ! $this->end_time) {
            return null;
        }

        return round($this->end_time->diffInSeconds($this->start_time) / 60, 1);
    }
}
