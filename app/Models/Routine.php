<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Routine extends Model
{
    protected $guarded = [];

    protected $casts = [
        'hevy_created_at' => 'datetime',
        'hevy_updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Routines are addressed by their Hevy id in URLs. */
    public function getRouteKeyName(): string
    {
        return 'hevy_id';
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(RoutineExercise::class)->orderBy('index');
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(RoutineFolder::class, 'folder_hevy_id', 'hevy_id');
    }

    public function workouts(): HasMany
    {
        return $this->hasMany(Workout::class, 'routine_hevy_id', 'hevy_id');
    }
}
