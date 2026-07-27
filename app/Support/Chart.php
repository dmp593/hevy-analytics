<?php

namespace App\Support;

/**
 * Builds Chart.js dataset arrays from simple labelled series so Blade views
 * stay declarative. A "series" is an array of ['label' => string, 'value' => number].
 */
class Chart
{
    /** Used when a multi() set does not name a colour. */
    public const DEFAULT_COLOR = '#4f46e5';

    /**
     * The shared, ordered chart palette.
     *
     * Views ask for a slot rather than naming a hex value, so every chart in the
     * app uses the same colours in the same order and the palette can change in
     * one place. Read from the CSS custom properties at render time is not
     * possible here (this runs server-side), so the values mirror the --series-*
     * tokens in app.css; the chart wrapper re-reads the live values in the
     * browser so dark mode still shifts them.
     */
    private const SERIES = [
        '#6366f1', '#38bdf8', '#22c55e', '#f59e0b', '#ef4444', '#d946ef',
    ];

    public static function series(int $index): string
    {
        return self::SERIES[$index % count(self::SERIES)];
    }

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
     * Only safe when the chart draws ONE series, because the values are indexed
     * positionally against whatever labels the chart was given. For two or more
     * series use multi(), which aligns them on a shared axis.
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

    /**
     * Align several series onto one shared x-axis.
     *
     * Series are indexed by label, not by position. Two series measured on
     * different dates -- a bodyweight logged without a body-fat reading, say --
     * would otherwise be drawn against each other's dates, silently shifting one
     * line and making every comparison drawn from the chart wrong. Points a
     * series does not have become null, and spanGaps keeps its line continuous.
     *
     * @param  array<int, array{series: array<int, array{label:string, value:float}>, label: string, color: string, fill?: bool}>  $sets
     * @return array{labels: array<int, string>, datasets: array<int, array<string, mixed>>}
     */
    public static function multi(array $sets): array
    {
        $labels = [];
        foreach ($sets as $set) {
            foreach ($set['series'] ?? [] as $point) {
                $labels[] = (string) $point['label'];
            }
        }

        // Collected as a list, not as array keys: PHP silently casts a numeric
        // string key to an integer, which would turn a year label like "2026"
        // back into an int and defeat the string comparison below.
        $labels = array_values(array_unique($labels));
        sort($labels, SORT_STRING);

        $datasets = [];
        foreach ($sets as $set) {
            $byLabel = [];
            foreach ($set['series'] ?? [] as $point) {
                $byLabel[(string) $point['label']] = $point['value'];
            }

            $fill = $set['fill'] ?? false;
            $color = $set['color'] ?? self::DEFAULT_COLOR;

            $datasets[] = [
                'label' => $set['label'],
                'data' => array_map(fn ($l) => $byLabel[$l] ?? null, $labels),
                'borderColor' => $color,
                'backgroundColor' => $fill ? $color.'1a' : 'transparent',
                'fill' => $fill,
                'tension' => 0.3,
                'spanGaps' => true,
            ];
        }

        return ['labels' => $labels, 'datasets' => $datasets];
    }
}
