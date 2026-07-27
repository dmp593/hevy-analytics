@props([
    'tone' => 'info',
    'title',
    'action' => null,
])

@php
    // The card that carries plain-English advice. Charts do not sell a
    // subscription; "your bench has stalled five weeks, add four triceps sets"
    // does. Tone is carried by an icon and a word as well as a colour, so it
    // survives colour-blindness and greyscale printing.
    $map = [
        'good' => ['bg-good-soft', 'text-good', 'border-good/20', 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
        'warn' => ['bg-warn-soft', 'text-warn', 'border-warn/20', 'M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z'],
        'bad' => ['bg-bad-soft', 'text-bad', 'border-bad/20', 'M12 9v3.75m-9.3 3.4h18.6a1.5 1.5 0 0 0 1.3-2.25L13.3 3.4a1.5 1.5 0 0 0-2.6 0L1.4 14.9a1.5 1.5 0 0 0 1.3 2.25Z'],
        'info' => ['bg-info-soft', 'text-info', 'border-info/20', 'M11.25 11.25l.04-.02a.75.75 0 0 1 1.06.98l-1.7 4.6a.75.75 0 0 0 1.06.98l.04-.02M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z'],
    ];
    [$bg, $fg, $border, $path] = $map[$tone] ?? $map['info'];
@endphp

<div {{ $attributes->merge(['class' => "flex items-start gap-3 rounded-xl border {$border} {$bg} px-4 py-3"]) }} role="status">
    <svg class="mt-0.5 h-5 w-5 shrink-0 {{ $fg }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}" />
    </svg>
    <div class="min-w-0 flex-1">
        <p class="text-sm font-semibold {{ $fg }}">{{ $title }}</p>
        <p class="mt-0.5 text-sm text-body">{{ $slot }}</p>
        @if ($action)
            <div class="mt-2">{{ $action }}</div>
        @endif
    </div>
</div>
