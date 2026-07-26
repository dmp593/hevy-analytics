<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProgressPhoto extends Model
{
    protected $fillable = ['date', 'angle', 'path', 'weight_kg', 'notes'];

    protected $casts = [
        'date' => 'date',
        'weight_kg' => 'float',
    ];

    /**
     * Delete the file whenever the record goes.
     *
     * Deleting an account removed the rows via the foreign key cascade and left
     * the images on disk indefinitely. These are photographs of someone's body:
     * under GDPR that is special-category data, and "we deleted your account but
     * kept the pictures" is not a position to be in. Hooking the model rather
     * than the controller means every delete path is covered, including the
     * cascade the controller never sees.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $photo) {
            if ($photo->path) {
                Storage::disk('local')->delete($photo->path);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
