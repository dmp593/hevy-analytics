@props([
    'sets' => [],
    'height' => 260,
    'empty' => 'No data for this range.',
])

@php
    $chart = \App\Support\Chart::multi($sets);
@endphp

@if (count($chart['labels']))
    <x-chart :labels="$chart['labels']" :datasets="$chart['datasets']" :height="$height" />
@else
    <x-empty-chart :height="$height">{{ $empty }}</x-empty-chart>
@endif
