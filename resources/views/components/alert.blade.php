@props(['level' => 'info', 'title' => null])

@php
$tones = [
    'success' => 'bg-good-soft border-good/30 text-good',
    'warning' => 'bg-warn-soft border-warn/30 text-warn',
    'bad' => 'bg-bad-soft border-bad/30 text-bad',
    'info' => 'bg-info-soft border-info/30 text-info',
];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-lg border px-4 py-3 '.($tones[$level] ?? $tones['info'])]) }}>
    @if($title)<div class="font-semibold text-sm">{{ $title }}</div>@endif
    <div class="text-sm">{{ $slot }}</div>
</div>
