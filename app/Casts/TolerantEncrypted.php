<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Like the built-in `encrypted` cast, but a value the current APP_KEY cannot
 * decrypt reads as null instead of throwing.
 *
 * The built-in cast turns an APP_KEY rotation into an outage: every page that
 * touches the attribute — the profile, the hourly sync, the AI settings —
 * starts throwing DecryptException for every affected account at once. After
 * a rotation the honest state of such a secret is "no longer on file": the
 * person pastes it again, exactly as they would after clearing it themselves.
 */
class TolerantEncrypted implements CastsAttributes
{
    public function get($model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return null;
        }
    }

    public function set($model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : Crypt::encryptString($value);
    }
}
