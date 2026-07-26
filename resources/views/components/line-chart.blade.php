@props([
    'series' => [],
    'label' => '',
    'color' => '#4f46e5',
    'fill' => true,
    'height' => 260,
    'empty' => 'No data for this range.',
])

@if(count($series))
    <x-chart
        :labels="\App\Support\Chart::labels($series)"
        :datasets="[\App\Support\Chart::line($series, $label, $color, $fill)]"
        :height="$height"
        :legend="false"
    />
@else
    <x-empty-chart :height="$height">{{ $empty }}</x-empty-chart>
@endif
