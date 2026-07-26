<?php

namespace App\Models;

use App\Science\Goals\GoalProfile;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Goal extends Model
{
    use HasFactory;

    protected $fillable = [
        'type', 'is_active', 'calorie_adjustment_pct', 'protein_g_per_kg',
        'fat_g_per_kg', 'target_rate_pct_bw_per_week', 'training', 'started_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'training' => 'array',
        'started_at' => 'date',
        'calorie_adjustment_pct' => 'float',
        'protein_g_per_kg' => 'float',
        'fat_g_per_kg' => 'float',
        'target_rate_pct_bw_per_week' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function profile(): GoalProfile
    {
        return GoalProfile::for($this);
    }
}
