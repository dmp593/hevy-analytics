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
                $labels[(string) $point['label']] = true;
            }
        }

        $labels = array_keys($labels);
        sort($labels);

        $datasets = [];
        foreach ($sets as $set) {
            $byLabel = [];
            foreach ($set['series'] ?? [] as $point) {
                $byLabel[(string) $point['label']] = $point['value'];
            }

            $fill = $set['fill'] ?? false;
            $color = $set['color'];

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
