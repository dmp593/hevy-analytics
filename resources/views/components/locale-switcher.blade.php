@props(['align' => 'right'])

@php
    $current = app()->getLocale();
    $locales = \App\Support\Locales::supported();
@endphp

@if (count($locales) > 1)
    <x-dropdown :align="$align" width="48">
        <x-slot name="trigger">
            <button type="button"
                    aria-label="{{ __('app.nav.language') }}"
                    class="inline-flex items-center gap-1 whitespace-nowrap rounded-md px-2 py-2 text-sm font-medium text-muted transition hover:text-body focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0 0c2.5-2.4 3.75-5.4 3.75-9S14.5 5.4 12 3m0 18c-2.5-2.4-3.75-5.4-3.75-9S9.5 5.4 12 3M3.6 9h16.8M3.6 15h16.8" />
                </svg>
                <span class="uppercase">{{ $current }}</span>
            </button>
        </x-slot>

        <x-slot name="content">
            @foreach ($locales as $code => $meta)
                {{-- POST, not GET: switching language changes stored state on the
                     account, and a GET that mutates is a CSRF hole. --}}
                <form method="POST" action="{{ route('locale.update', $code) }}">
                    @csrf
                    <button type="submit"
                            @if ($code === $current) aria-current="true" @endif
                            class="flex w-full items-center justify-between px-4 py-2 text-start text-sm leading-5 transition hover:bg-surface-sunk focus:bg-surface-sunk focus:outline-hidden {{ $code === $current ? 'font-semibold text-brand-ink' : 'text-body' }}">
                        {{ $meta['native'] }}
                        @if ($code === $current)
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7" />
                            </svg>
                        @endif
                    </button>
                </form>
            @endforeach
        </x-slot>
    </x-dropdown>
@endif
