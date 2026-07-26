@props(['level' => 'info', 'title' => null])

@php
$tones = [
    'success' => 'bg-green-50 border-green-200 text-green-800',
    'warning' => 'bg-amber-50 border-amber-200 text-amber-800',
    'bad' => 'bg-red-50 border-red-200 text-red-800',
    'info' => 'bg-blue-50 border-blue-200 text-blue-800',
];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-lg border px-4 py-3 '.($tones[$level] ?? $tones['info'])]) }}>
    @if($title)<div class="font-semibold text-sm">{{ $title }}</div>@endif
    <div class="text-sm">{{ $slot }}</div>
</div>
