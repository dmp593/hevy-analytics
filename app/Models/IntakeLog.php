<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntakeLog extends Model
{
    protected $fillable = [
        'date', 'calories', 'protein_g', 'fat_g', 'carb_g', 'steps', 'sleep_minutes', 'weight_kg', 'fat_percent', 'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'calories' => 'float',
        'protein_g' => 'float',
        'fat_g' => 'float',
        'carb_g' => 'float',
        'steps' => 'integer',
        'sleep_minutes' => 'integer',
        'weight_kg' => 'float',
        'fat_percent' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
