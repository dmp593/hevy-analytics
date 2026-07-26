@props([
    'title' => null,
    'subtitle' => null,
    'actions' => null,
    'flush' => false,
])

<section {{ $attributes->merge(['class' => 'rounded-xl border border-line bg-surface shadow-xs']) }}>
    @if ($title || $actions)
        <header class="flex items-start justify-between gap-4 px-5 pt-4 {{ $flush ? 'pb-4' : '' }}">
            <div class="min-w-0">
                @if ($title)
                    <h2 class="text-sm font-semibold text-ink">{{ $title }}</h2>
                @endif
                @if ($subtitle)
                    <p class="mt-0.5 text-xs text-muted">{{ $subtitle }}</p>
                @endif
            </div>
            @if ($actions)
                <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
            @endif
        </header>
    @endif

    <div class="{{ $flush ? '' : 'px-5 pb-5 '.($title ? 'pt-4' : 'pt-5') }}">
        {{ $slot }}
    </div>
</section>
