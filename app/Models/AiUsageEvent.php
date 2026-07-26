<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per AI request attempted. See the migration for why the quota counts
 * attempts rather than stored results.
 */
class AiUsageEvent extends Model
{
    protected $fillable = [
        'scope',
        'provider',
        'model',
        'outcome',
        'prompt_tokens',
        'completion_tokens',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
