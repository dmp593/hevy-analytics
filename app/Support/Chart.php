<?php

namespace App\Support;

/**
 * Builds Chart.js dataset arrays from simple labelled series so Blade views
 * stay declarative. A "series" is an array of ['label' => string, 'value' => number].
 */
class Chart
{
    /** Extract the x-axis labels from a series. */
    public static function labels(array $series): array
    {
        return array_map(fn ($p) => $p['label'], $series);
    }

    /** Extract the y-axis values from a series. */
    public static function values(array $series): array
    {
        return array_map(fn ($p) => $p['value'], $series);
    }

    /**
     * A single line/area dataset.
     *
     * @param  array<int, array{label:string, value:float}>  $series
     */
    public static function line(array $series, string $label, string $color, bool $fill = true): array
    {
        return [
            'label' => $label,
            'data' => self::values($series),
            'borderColor' => $color,
            'backgroundColor' => $fill ? $color.'1a' : 'transparent',
            'fill' => $fill,
            'tension' => 0.3,
        ];
    }
}
