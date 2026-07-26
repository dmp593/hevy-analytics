@props([
    'title',
    'subtitle' => null,
    'meta' => null,
    'width' => '7xl',
])

{{-- The page shell, once.

     This markup was copy-pasted into sixteen views with four different
     max-widths, so pages silently disagreed about how wide they were. Passing a
     title here also sets the browser tab title, which every page previously
     shared. --}}
<x-app-layout :title="$title">
    <x-slot name="header">
        <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1">
            <div class="min-w-0">
                <h1 class="truncate text-xl font-semibold tracking-tight text-ink">{{ $title }}</h1>
                @if ($subtitle)
                    <p class="mt-0.5 text-sm text-muted">{{ $subtitle }}</p>
                @endif
            </div>
            @if ($meta)
                <div class="text-xs text-muted">{{ $meta }}</div>
            @endif
        </div>
    </x-slot>

    <div {{ $attributes->merge(['class' => "mx-auto max-w-{$width} px-4 py-8 sm:px-6 lg:px-8"]) }}>
        {{ $slot }}
    </div>
</x-app-layout>
