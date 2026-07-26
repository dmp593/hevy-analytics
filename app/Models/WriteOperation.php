<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WriteOperation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'response' => 'array',
        'revert_info' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
