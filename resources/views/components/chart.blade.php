@props([
    'type' => 'line',
    'labels' => [],
    'datasets' => [],
    'height' => 260,
    'options' => [],
    'legend' => true,
    'summary' => null,
])

@php
    $config = [
        'type' => $type,
        'legend' => $legend,
        // An empty PHP array encodes as [] rather than {}; Chart.js wants an
        // object here, so force one.
        'data' => ['labels' => $labels, 'datasets' => $datasets],
        'options' => (object) $options,
    ];

    // A <canvas> is opaque to assistive technology. Describe what it plots and
    // the range it covers so the chart is not simply missing for screen readers.
    // Labels are not guaranteed to be a 0-indexed list, so read the ends by key
    // rather than by position.
    $labelList = array_values((array) $labels);
    $seriesNames = collect($datasets)->pluck('label')->filter()->implode(', ');
    $first = $labelList[0] ?? null;
    $last = $labelList ? end($labelList) : null;

    $description = $summary ?? trim(sprintf(
        '%s chart%s%s.',
        ucfirst($type),
        $seriesNames !== '' ? ' showing '.$seriesNames : '',
        $first && $last ? sprintf(' from %s to %s', $first, $last) : '',
    ));
@endphp

<div style="height: {{ $height }}px" class="relative">
    <canvas
        x-data="chart"
        x-init="mount()"
        role="img"
        aria-label="{{ $description }}"
        data-chart-config="{{ json_encode($config, JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_TAG) }}"
    ></canvas>
</div>
