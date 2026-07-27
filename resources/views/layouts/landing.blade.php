<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @include('partials.head-meta')

        <title>{{ __('app.landing.meta_title') }}</title>
        <meta name="description" content="{{ __('app.landing.meta_description') }}">

        {{-- Applied before first paint, same as the app shell: the landing page
             is the one page a dark-mode user sees before anything is cached, so
             a white flash here is the first impression. --}}
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

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-canvas font-sans text-body antialiased">
        <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-brand focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-on-fill">
            {{ __('app.common.skip_to_content') }}
        </a>

        <header class="border-b border-subtle">
            <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-5 py-4">
                <a href="{{ route('home') }}" class="flex min-h-11 items-center gap-2 text-sm font-semibold text-ink">
                    <x-application-logo class="h-7 w-7 text-brand" />
                    {{ __('app.brand') }}
                </a>

                <div class="flex items-center gap-1">
                    <x-theme-toggle />
                    <x-locale-switcher />
                    <a href="{{ route('login') }}" class="ml-2 inline-flex min-h-11 items-center whitespace-nowrap rounded-lg px-3 text-sm font-medium text-body hover:bg-surface-sunk hover:text-ink">
                        {{ __('app.landing.sign_in') }}
                    </a>
                </div>
            </div>
        </header>

        <main id="main" tabindex="-1">
            {{ $slot }}
        </main>

        <footer class="border-t border-subtle">
            <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 px-5 py-8 text-xs text-muted">
                <p>{{ __('app.landing.footer_note') }}</p>
                <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center underline hover:text-ink">{{ __('app.landing.sign_in') }}</a>
            </div>
        </footer>
    </body>
</html>
