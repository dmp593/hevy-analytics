<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgressPhoto extends Model
{
    protected $fillable = ['date', 'angle', 'path', 'weight_kg', 'notes'];

    protected $casts = [
        'date' => 'date',
        'weight_kg' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
