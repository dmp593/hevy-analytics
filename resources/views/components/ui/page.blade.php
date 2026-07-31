@props([
    'title',
    'subtitle' => null,
    'meta' => null,
    'actions' => null,
    'width' => '7xl',
    'help' => null,
    'helpAnchor' => null,
])

@php
    // Literal class names, never "max-w-{$width}". Tailwind generates utilities
    // by scanning source text, so an interpolated class name is invisible to it
    // and the utility is simply never emitted — no error, the page just renders
    // full-width. Only max-w-7xl survived, because it appears literally
    // elsewhere, which is exactly why this went unnoticed on the dashboard.
    $max = [
        'sm' => 'max-w-sm',
        'md' => 'max-w-md',
        'lg' => 'max-w-lg',
        'xl' => 'max-w-xl',
        '2xl' => 'max-w-2xl',
        '3xl' => 'max-w-3xl',
        '4xl' => 'max-w-4xl',
        '5xl' => 'max-w-5xl',
        '6xl' => 'max-w-6xl',
        '7xl' => 'max-w-7xl',
    ][$width] ?? 'max-w-7xl';
@endphp

{{-- The page shell, once.

     This markup was copy-pasted into sixteen views with four different
     max-widths, so pages silently disagreed about how wide they were. Passing a
     title here also sets the browser tab title, which every page previously
     shared, and emits the page's only <h1>: the copy-pasted version opened at
     <h2>, so every page in the app was missing a top-level heading. --}}
<x-app-layout :title="$title">
    <x-slot name="header">
        <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-2">
            <div class="min-w-0">
                <h1 class="truncate text-xl font-semibold tracking-tight text-ink">{{ $title }}</h1>
                @if ($subtitle)
                    <p class="mt-0.5 text-sm text-muted">{{ $subtitle }}</p>
                @endif

                {{-- The page's own documentation, folded away. A native
                     <details> keeps it one tap from every screen without
                     adding a line of noise for people who already know —
                     the complaint this answers was "too much information
                     per screen AND I don't know what each feature is for",
                     which only a collapsed explainer satisfies at once. --}}
                @if ($help)
                    <details class="mt-1.5 max-w-2xl">
                        <summary class="inline-flex min-h-8 cursor-pointer list-none items-center text-xs text-muted underline decoration-dotted underline-offset-4 hover:text-body [&::-webkit-details-marker]:hidden">
                            {{ __('app.help.toggle') }}
                        </summary>
                        <div class="mt-2 rounded-lg border border-line bg-surface-sunk px-4 py-3">
                            <p class="text-sm text-body">{{ $help }}</p>
                            <a href="{{ route('guide') }}{{ $helpAnchor ? '#'.$helpAnchor : '' }}" class="mt-1 inline-flex min-h-10 items-center text-xs font-medium text-brand-ink">
                                {{ __('app.help.guide_link') }}
                            </a>
                        </div>
                    </details>
                @endif
            </div>

            @if ($actions || $meta)
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    @if ($meta)
                        <div class="text-xs text-muted">{{ $meta }}</div>
                    @endif
                    @if ($actions)
                        <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
                    @endif
                </div>
            @endif
        </div>
    </x-slot>

    <div {{ $attributes->merge(['class' => "mx-auto {$max} px-4 py-8 sm:px-6 lg:px-8"]) }}>
        {{ $slot }}
    </div>
</x-app-layout>
