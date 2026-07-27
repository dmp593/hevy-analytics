@props([
    'series' => [],
    'label' => '',
    'color' => '#4f46e5',
    'fill' => true,
    'height' => 260,
    'empty' => null,
])

@if(count($series))
    <x-chart
        :labels="\App\Support\Chart::labels($series)"
        :datasets="[\App\Support\Chart::line($series, $label, $color, $fill)]"
        :height="$height"
        :legend="false"
    />
@else
    <x-empty-chart :height="$height">{{ $empty ?? __('app.chart.no_data') }}</x-empty-chart>
@endif
