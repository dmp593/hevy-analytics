@php
    $sections = \App\Support\Navigation::sections();
    $active = \App\Support\Navigation::activeSection();
@endphp

<nav x-data="{ open: false }" class="border-b border-line bg-surface" aria-label="{{ __('app.nav.primary') }}">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between gap-4">
            <div class="flex min-w-0 items-center gap-8">
                <a href="{{ route('dashboard') }}" class="inline-flex min-h-11 shrink-0 items-center whitespace-nowrap text-lg font-bold text-brand-ink">
                    {{ __('app.brand') }}
                </a>

                {{-- Four sections, not twelve links. The pages inside a section
                     appear as sub-navigation below once you are in it, so depth
                     is revealed on demand instead of dumped in the header. --}}
                <div class="hidden items-center gap-1 lg:flex">
                    @foreach ($sections as $section)
                        @php $isActive = $active && $active['key'] === $section['key']; @endphp
                        <a href="{{ route($section['route']) }}"
                           @if ($isActive) aria-current="page" @endif
                           class="rounded-lg px-3 py-2 text-sm font-medium transition {{ $isActive ? 'bg-brand-soft text-brand-ink' : 'text-muted hover:bg-surface-sunk hover:text-ink' }}">
                            {{ $section['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="hidden shrink-0 items-center gap-1 lg:flex">
                {{-- The palette's visible door: a search pill naming its own
                     shortcut with the modifier for the keyboard at hand. --}}
                <button type="button" @click="window.dispatchEvent(new CustomEvent('open-palette'))"
                        class="mr-1 inline-flex min-h-9 items-center gap-2 rounded-lg border border-line bg-surface-sunk px-3 text-sm text-muted transition hover:text-ink"
                        aria-label="{{ __('app.palette.open_aria') }}">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.45 4.4l3.07 3.08a1 1 0 0 1-1.41 1.41l-3.08-3.07A7 7 0 0 1 2 9Z" clip-rule="evenodd" />
                    </svg>
                    <span>{{ __('app.palette.hint') }}</span>
                    <kbd x-data="{ mac: /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent) }"
                         x-text="mac ? '\u2318K' : 'Ctrl K'"
                         class="rounded-sm border border-line bg-surface px-1.5 py-0.5 font-mono text-[11px]">Ctrl K</kbd>
                </button>

                <form method="POST" action="{{ route('sync') }}">
                    @csrf
                    <x-ui.button size="sm" class="whitespace-nowrap">{{ __('app.nav.sync') }}</x-ui.button>
                </form>

                <x-theme-toggle />
                <x-locale-switcher />

                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button type="button" class="inline-flex items-center gap-1 whitespace-nowrap rounded-lg px-2 py-2 text-sm font-medium text-muted transition hover:text-ink">
                            <span class="max-w-32 truncate">{{ Auth::user()->name }}</span>
                            <svg class="h-4 w-4 fill-current" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">{{ __('app.nav.profile') }}</x-dropdown-link>
                        <x-dropdown-link :href="route('billing')">{{ __('app.nav.billing') }}</x-dropdown-link>
                        @if (auth()->user()->is_admin)
                            <x-dropdown-link :href="route('admin.users')">{{ __('app.nav.admin') }}</x-dropdown-link>
                        @endif
                        <x-dropdown-link :href="route('guide')">{{ __('app.nav.guide') }}</x-dropdown-link>

                        {{-- The data doors, gathered: import, convert, write-back
                             and export were scattered behind page-level links, and
                             a paid feature nobody can find is not a feature. --}}
                        <div class="border-t border-subtle px-4 pb-1 pt-2 text-xs font-semibold uppercase tracking-wide text-faint">{{ __('app.nav.my_data') }}</div>
                        <x-dropdown-link :href="route('import')">{{ __('app.nav.import') }}</x-dropdown-link>
                        <x-dropdown-link :href="route('convert')">{{ __('app.nav.convert') }}</x-dropdown-link>
                        <x-dropdown-link :href="route('write.index')">{{ __('app.nav.write_operations') }}</x-dropdown-link>
                        <x-dropdown-link :href="route('settings.export')">{{ __('app.nav.export') }}</x-dropdown-link>

                        <div class="border-t border-subtle"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">{{ __('app.nav.log_out') }}</x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <div class="flex items-center gap-1 lg:hidden">
                <button type="button" @click="window.dispatchEvent(new CustomEvent('open-palette'))"
                        class="inline-flex items-center justify-center rounded-lg p-2 text-muted transition hover:bg-surface-sunk hover:text-ink"
                        aria-label="{{ __('app.palette.open_aria') }}">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.45 4.4l3.07 3.08a1 1 0 0 1-1.41 1.41l-3.08-3.07A7 7 0 0 1 2 9Z" clip-rule="evenodd" />
                    </svg>
                </button>
                <x-theme-toggle />
                <button type="button" @click="open = ! open" :aria-expanded="open" aria-controls="mobile-menu"
                        aria-label="{{ __('app.nav.toggle') }}"
                        class="inline-flex items-center justify-center rounded-lg p-2 text-muted transition hover:bg-surface-sunk hover:text-ink">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <path x-show="!open" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path x-show="open" x-cloak stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    {{-- Sub-navigation for the section you are in. Only rendered when a section
         has more than one page — a single-page section needs no tabs. --}}
    @if ($active && count($active['children']) > 1)
        <div class="hidden border-t border-subtle bg-surface-sunk/40 lg:block">
            <div class="mx-auto flex max-w-7xl gap-1 overflow-x-auto px-4 sm:px-6 lg:px-8" aria-label="{{ $active['label'] }}">
                @foreach ($active['children'] as $child)
                    @php $isHere = request()->routeIs($child['pattern']); @endphp
                    <a href="{{ route($child['route']) }}"
                       @if ($isHere) aria-current="page" @endif
                       class="-mb-px whitespace-nowrap border-b-2 px-3 py-2.5 text-sm transition {{ $isHere ? 'border-brand font-medium text-brand-ink' : 'border-transparent text-muted hover:text-ink' }}">
                        {{ $child['label'] }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div id="mobile-menu" x-show="open" x-cloak class="border-t border-line lg:hidden">
        @foreach ($sections as $section)
            <div class="border-b border-subtle py-2">
                <p class="px-4 pb-1 pt-1 text-xs font-semibold uppercase tracking-wide text-faint">{{ $section['label'] }}</p>
                @foreach ($section['children'] as $child)
                    <x-responsive-nav-link :href="route($child['route'])" :active="request()->routeIs($child['pattern'])">
                        {{ $child['label'] }}
                    </x-responsive-nav-link>
                @endforeach
            </div>
        @endforeach

        <div class="py-3">
            <div class="px-4 pb-2">
                <div class="text-base font-medium text-ink">{{ Auth::user()->name }}</div>
                <div class="text-sm text-muted">{{ Auth::user()->email }}</div>
            </div>
            <form method="POST" action="{{ route('sync') }}">
                @csrf
                <x-responsive-nav-link :href="route('sync')" onclick="event.preventDefault(); this.closest('form').submit();">{{ __('app.nav.sync') }}</x-responsive-nav-link>
            </form>
            <x-responsive-nav-link :href="route('profile.edit')">{{ __('app.nav.profile') }}</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('billing')">{{ __('app.nav.billing') }}</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('guide')">{{ __('app.nav.guide') }}</x-responsive-nav-link>
            <p class="px-4 pb-1 pt-3 text-xs font-semibold uppercase tracking-wide text-faint">{{ __('app.nav.my_data') }}</p>
            <x-responsive-nav-link :href="route('import')">{{ __('app.nav.import') }}</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('convert')">{{ __('app.nav.convert') }}</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('write.index')">{{ __('app.nav.write_operations') }}</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('settings.export')">{{ __('app.nav.export') }}</x-responsive-nav-link>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-responsive-nav-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">{{ __('app.nav.log_out') }}</x-responsive-nav-link>
            </form>
        </div>
    </div>
</nav>
