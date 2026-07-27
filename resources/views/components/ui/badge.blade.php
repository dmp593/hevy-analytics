@props(['tone' => 'neutral'])

@php
    // Every pairing here is at least 4.5:1. The previous badges used 10px
    // text-faint at 2.58:1, which is unreadable rather than subtle.
    $classes = match ($tone) {
        'good' => 'bg-good-soft text-good',
        'warn' => 'bg-warn-soft text-warn',
        'bad' => 'bg-bad-soft text-bad',
        'info' => 'bg-info-soft text-info',
        'brand' => 'bg-brand-soft text-brand-ink',
        default => 'bg-surface-sunk text-muted',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap {$classes}"]) }}>
    {{ $slot }}
</span>
