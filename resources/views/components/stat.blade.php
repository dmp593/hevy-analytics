@props(['label', 'value', 'unit' => '', 'sub' => null, 'tone' => 'default', 'tip' => null, 'tipTitle' => null, 'tipAnchor' => null])

@php
$tones = [
    'default' => 'bg-surface',
    'good' => 'bg-good-soft border-good/30',
    'warn' => 'bg-warn-soft border-warn/30',
    'bad' => 'bg-bad-soft border-bad/30',
    'accent' => 'bg-brand-soft border-brand',
];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl border border-line p-4 shadow-xs '.($tones[$tone] ?? $tones['default'])]) }}>
    <div class="text-xs font-medium uppercase tracking-wide text-muted">
        {{ $label }}@if($tip)<x-info :title="$tipTitle ?? $label" :text="$tip" :anchor="$tipAnchor" />@endif
    </div>
    <div class="mt-1 flex items-baseline gap-1">
        <span class="text-2xl font-bold text-ink">{{ $value }}</span>
        @if($unit)<span class="text-sm text-muted">{{ $unit }}</span>@endif
    </div>
    @if($sub)<div class="mt-1 text-xs text-muted">{{ $sub }}</div>@endif
</div>
