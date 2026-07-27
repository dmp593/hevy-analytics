@props([
    'sets' => [],
    'height' => 260,
    'empty' => null,
])

@php
    $chart = \App\Support\Chart::multi($sets);
@endphp

@if (count($chart['labels']))
    <x-chart :labels="$chart['labels']" :datasets="$chart['datasets']" :height="$height" />
@else
    <x-empty-chart :height="$height">{{ $empty ?? __('app.chart.no_data') }}</x-empty-chart>
@endif
