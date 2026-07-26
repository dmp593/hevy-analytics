@props(['lines' => 3, 'height' => null])

{{-- Placeholder while something loads. Sized to the content it replaces so the
     page does not jump when the real thing arrives. --}}
<div {{ $attributes->only('class') }} aria-hidden="true">
    @if ($height)
        <div class="motion-safe:animate-pulse rounded-lg bg-surface-sunk" style="height: {{ $height }}px"></div>
    @else
        <div class="space-y-2 motion-safe:animate-pulse">
            @for ($i = 0; $i < $lines; $i++)
                <div class="h-3 rounded bg-surface-sunk" style="width: {{ [100, 92, 78, 85][$i % 4] }}%"></div>
            @endfor
        </div>
    @endif
</div>
