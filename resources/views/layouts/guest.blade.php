<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ __('app.brand') }}</title>

        {{-- Applied before first paint: setting the class from a deferred module
             would flash a white page at every dark-mode user on every navigation. --}}
        <script>
            (() => {
                const pref = localStorage.getItem('theme') || 'system';
                const dark = pref === 'dark'
                    || (pref === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', dark);
            })();
        </script>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-ink antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-surface-sunk">
            <div class="w-full max-w-md px-6 flex justify-end gap-1 sm:px-0">
                <x-theme-toggle />
                <x-locale-switcher />
            </div>
            <div>
                <a href="/">
                    <x-application-logo class="h-14 w-14 text-brand" />
                </a>
            </div>

            <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-surface shadow-md overflow-hidden sm:rounded-lg">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
