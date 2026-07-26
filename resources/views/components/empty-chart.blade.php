@props(['height' => 260])

{{-- Charts used to render as a blank box when there was nothing to plot, which
     is indistinguishable from a chart that failed to load. Say so instead. --}}
<div style="min-height: {{ $height }}px"
     class="flex flex-col items-center justify-center gap-2 rounded-md border border-dashed border-gray-200 bg-gray-50/50 px-4 py-6 text-center">
    <svg class="h-6 w-6 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v16.5A1.5 1.5 0 0 0 4.5 21H21M7 15l3.5-3.5 3 3L20 8" />
    </svg>
    <p class="text-sm text-gray-500">{{ $slot }}</p>
</div>
