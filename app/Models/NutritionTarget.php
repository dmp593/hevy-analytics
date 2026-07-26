<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NutritionTarget extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'basis' => 'array',
        'weight_kg' => 'float',
        'lean_mass_kg' => 'float',
        'bmr' => 'float',
        'tdee' => 'float',
        'target_calories' => 'float',
        'protein_g' => 'float',
        'fat_g' => 'float',
        'carb_g' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
