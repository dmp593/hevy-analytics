@props(['type' => 'line', 'labels' => [], 'datasets' => [], 'height' => 260, 'options' => [], 'legend' => true])

@php
$config = [
    'type' => $type,
    'legend' => $legend,
    'data' => ['labels' => $labels, 'datasets' => $datasets],
    'options' => $options,
];
@endphp

<div style="height: {{ $height }}px" class="relative">
    <canvas x-data="chart" data-chart-config="{{ json_encode($config, JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_TAG) }}" x-init="mount()"></canvas>
</div>
