@php
    $sections = \App\Support\Navigation::sections();
    $active = \App\Support\Navigation::activeSection();

    // One hand, one thumb: the four sections as a fixed bar. The hamburger
    // menu keeps the full page list; this is for switching context without
    // opening anything.
    $icons = [
        'today' => 'M3 10.5 12 3l9 7.5M5.25 9v10.5h4.5v-6h4.5v6h4.5V9',
        'training' => 'M3.75 12h16.5M6 8.25v7.5M18 8.25v7.5M8.25 6.75v10.5M15.75 6.75v10.5',
        'body' => 'M12 7.5a2.25 2.25 0 1 0 0-4.5 2.25 2.25 0 0 0 0 4.5ZM9 9.75h6M12 9.75V15m0 0-2.25 6M12 15l2.25 6',
        'nutrition' => 'M12 6.75c1.5-3 5.25-3 6.75-.75 1.5 2.25.75 6.75-2.25 10.5-1.5 1.87-3 3-4.5 3s-3-1.13-4.5-3C4.5 12.75 3.75 8.25 5.25 6c1.5-2.25 5.25-2.25 6.75.75Zm0 0V4.5m0-1.5c0 .75.75 1.5 1.5 1.5',
    ];
@endphp

<nav class="fixed inset-x-0 bottom-0 z-40 border-t border-line bg-surface pb-[env(safe-area-inset-bottom)] lg:hidden"
     aria-label="{{ __('app.nav.primary') }}">
    <div class="mx-auto grid max-w-md grid-cols-4">
        @foreach ($sections as $section)
            @php $isActive = $active && $active['key'] === $section['key']; @endphp
            <a href="{{ route($section['route']) }}"
               @if ($isActive) aria-current="page" @endif
               class="flex min-h-14 flex-col items-center justify-center gap-0.5 text-[11px] font-medium transition {{ $isActive ? 'text-brand-ink' : 'text-muted hover:text-ink' }}">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="{{ $icons[$section['key']] ?? $icons['today'] }}" />
                </svg>
                {{ $section['label'] }}
            </a>
        @endforeach
    </div>
</nav>
