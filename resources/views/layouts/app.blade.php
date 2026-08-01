<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @include('partials.head-meta')

        {{-- Per-page title: every page shared one, which is poor for tabs, history and screen readers. --}}
        <title>{{ isset($title) ? $title.' · ' : '' }}{{ __('app.brand') }}</title>

        {{-- Applied before first paint: setting the class from a deferred module
             would flash a white page at every dark-mode user on every navigation. --}}
        <script>
            (() => {
                const pref = localStorage.getItem('theme') || 'system';
                const dark = pref === 'dark'
                    || (pref === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', dark);
                // Keep the phone's status bar in step with a manual override
                // (the metas' media queries only know the OS scheme).
                document.querySelectorAll('meta[name="theme-color"]').forEach((m) => {
                    m.setAttribute('content', dark ? '#0c1015' : '#f9fafb');
                    m.removeAttribute('media');
                });
            })();
        </script>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @if (config('cashier.client_side_token') || config('cashier.seller_id'))
            {{-- Paddle's overlay checkout.

                 The @paddleJS directive rather than a component: Blade resolves
                 <x-...> tags at COMPILE time, so an unconfigured install would
                 fail to compile this file even with the @if above it. --}}
            @paddleJS
        @endif
    </head>
    <body class="font-sans antialiased">
        {{-- Keyboard users should be able to jump past twelve nav links. --}}
        <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-brand focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-on-fill">
            {{ __('app.common.skip_to_content') }}
        </a>

        <div class="min-h-screen bg-canvas">
            @if (auth()->user()?->is_demo)
                {{-- Always visible, on every page: a visitor deep in the muscle
                     page must still know none of this is theirs, and must be one
                     tap from making it theirs. --}}
                <div class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 bg-brand px-4 py-2 text-center text-sm font-medium text-on-fill">
                    <span>{{ __('app.demo.banner') }}</span>
                    <form method="POST" action="{{ route('demo.leave') }}">
                        @csrf
                        <button type="submit" class="min-h-11 underline underline-offset-2 hover:opacity-90">
                            {{ __('app.demo.create_mine') }}
                        </button>
                    </form>
                </div>
            @endif
            @include('layouts.navigation')

            {{-- Cmd+K / Ctrl+K, on every page an athlete is signed in to. --}}
            <x-command-palette />

            <!-- Page Heading -->
            @isset($header)
                <header class="border-b border-line bg-surface">
                    <div class="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            {{-- The bottom padding keeps the last card reachable above the
                 fixed mobile tab bar; at lg the bar is gone and so is it. --}}
            <main id="main" tabindex="-1" class="pb-20 lg:pb-0">
                {{ $slot }}
            </main>

            @include('layouts.bottom-nav')
        </div>
    </body>
</html>
