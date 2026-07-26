@props(['title' => null, 'icon' => true])

{{-- Say what is missing and what to do about it. A blank panel is
     indistinguishable from a panel that failed to load. --}}
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-line bg-surface-sunk/50 px-4 py-8 text-center']) }}>
    @if ($icon)
        <svg class="h-6 w-6 text-faint" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v16.5A1.5 1.5 0 0 0 4.5 21H21M7 15l3.5-3.5 3 3L20 8" />
        </svg>
    @endif
    @if ($title)
        <p class="text-sm font-medium text-body">{{ $title }}</p>
    @endif
    <p class="max-w-sm text-sm text-muted">{{ $slot }}</p>
</div>
