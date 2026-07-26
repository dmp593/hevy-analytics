@props(['label', 'value', 'unit' => '', 'sub' => null, 'tone' => 'default', 'tip' => null, 'tipTitle' => null])

@php
$tones = [
    'default' => 'bg-white',
    'good' => 'bg-green-50 border-green-200',
    'warn' => 'bg-amber-50 border-amber-200',
    'bad' => 'bg-red-50 border-red-200',
    'accent' => 'bg-indigo-50 border-indigo-200',
];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl border border-gray-200 p-4 shadow-xs '.($tones[$tone] ?? $tones['default'])]) }}>
    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">
        {{ $label }}@if($tip)<x-info :title="$tipTitle ?? $label" :text="$tip" />@endif
    </div>
    <div class="mt-1 flex items-baseline gap-1">
        <span class="text-2xl font-bold text-gray-900">{{ $value }}</span>
        @if($unit)<span class="text-sm text-gray-500">{{ $unit }}</span>@endif
    </div>
    @if($sub)<div class="mt-1 text-xs text-gray-500">{{ $sub }}</div>@endif
</div>
