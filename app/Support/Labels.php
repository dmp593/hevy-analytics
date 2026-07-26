<?php

namespace App\Support;

/**
 * Display names for values the domain stores as keys.
 *
 * Muscle groups and volume classifications are stored and computed as stable
 * identifiers ("lower_back", "below_maintenance") — which is correct, because a
 * service should never decide what language the reader speaks. This turns them
 * into words at the last possible moment.
 *
 * Falls back to a humanised form of the key rather than showing a missing
 * translation string: Hevy can add a muscle group we have not named yet, and
 * "Rear delts" reads better than "app.muscles.rear_delts".
 */
class Labels
{
    public static function muscle(?string $key): string
    {
        return self::translate('app.muscles.', $key);
    }

    public static function volumeStatus(?string $key): string
    {
        return self::translate('app.volume_status.', $key);
    }

    private static function translate(string $namespace, ?string $key): string
    {
        if ($key === null || $key === '') {
            return '—';
        }

        $translated = __($namespace.$key);

        // Laravel returns the key itself when there is no translation.
        return is_string($translated) && ! str_starts_with($translated, $namespace)
            ? $translated
            : ucfirst(str_replace('_', ' ', $key));
    }
}
