@props(['title' => null, 'subtitle' => null])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-line bg-surface p-5 shadow-xs']) }}>
    @if($title)
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-semibold text-ink">{{ $title }}</h3>
                @if($subtitle)<p class="text-xs text-muted">{{ $subtitle }}</p>@endif
            </div>
            @isset($actions){{ $actions }}@endisset
        </div>
    @endif
    {{ $slot }}
</div>
