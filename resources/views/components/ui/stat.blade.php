@props([
    'label',
    'value',
    'unit' => null,
    'sub' => null,
    'tone' => 'default',
    'tip' => null,
    'trend' => null,
])

@php
    $hasValue = $value !== null && $value !== '' && $value !== '—';

    // Tone colours the VALUE, never the whole tile: a wall of coloured boxes
    // stops meaning anything.
    //
    // A missing value is always neutral, whatever tone the caller passed. A
    // caller writing `$x > 0.5 ? 'warn' : 'good'` produces 'good' when $x is
    // null, so an athlete who had never logged a waist measurement was shown a
    // reassuring green dash. Judging nothing is not the same as judging it fine.
    $valueTone = ! $hasValue ? 'text-muted' : match ($tone) {
        'good' => 'text-good',
        'warn' => 'text-warn',
        'bad' => 'text-bad',
        'accent' => 'text-brand-ink',
        default => 'text-ink',
    };
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl border border-line bg-surface px-4 py-3 shadow-xs']) }}>
    <div class="flex items-center gap-1 text-xs font-medium uppercase tracking-wide text-muted">
        <span class="truncate">{{ $label }}</span>
        @if ($tip)
            <x-info :text="$tip" :title="$label" />
        @endif
    </div>

    <div class="mt-1 flex items-baseline gap-1">
        <span class="text-2xl font-semibold tabular-nums {{ $valueTone }}">{{ $hasValue ? $value : '—' }}</span>
        {{-- Suppress the unit when there is no value: "— kg" reads as a broken
             number rather than as missing data. --}}
        @if ($unit && $hasValue)
            <span class="text-xs text-muted">{{ $unit }}</span>
        @endif
        @if ($trend !== null && $hasValue)
            <span class="ml-1 text-xs font-medium tabular-nums {{ $trend > 0 ? 'text-good' : ($trend < 0 ? 'text-bad' : 'text-muted') }}">
                {{ $trend > 0 ? '↑' : ($trend < 0 ? '↓' : '→') }} {{ abs($trend) }}
            </span>
        @endif
    </div>

    @if ($sub)
        <div class="mt-0.5 truncate text-xs text-muted">{{ $sub }}</div>
    @endif
</div>
