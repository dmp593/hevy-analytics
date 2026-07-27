@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'submit',
])

@php
    $base = 'inline-flex items-center justify-center gap-1.5 rounded-lg font-semibold transition '
        .'disabled:cursor-not-allowed disabled:opacity-50';

    // min-h keeps every size at a usable thumb target (Apple's floor is 44px)
    // without inflating the horizontal padding that gives each size its look.
    $sizes = [
        'sm' => 'min-h-10 px-3 py-1.5 text-xs',
        'md' => 'min-h-11 px-4 py-2 text-sm',
        'lg' => 'min-h-12 px-5 py-2.5 text-sm',
    ];

    $variants = [
        'primary' => 'bg-brand text-on-fill hover:bg-brand-hover',
        'secondary' => 'border border-line bg-surface text-body hover:bg-surface-sunk',
        'ghost' => 'text-muted hover:bg-surface-sunk hover:text-ink',
        'danger' => 'bg-bad text-on-fill hover:opacity-90',
    ];

    $classes = $base.' '.($sizes[$size] ?? $sizes['md']).' '.($variants[$variant] ?? $variants['primary']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
