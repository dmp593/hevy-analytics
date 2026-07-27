<div x-data="theme" class="relative">
    {{-- Three options rather than a binary switch: "system" is the default and a
         real choice, so the control has to be able to express it. --}}
    <x-dropdown align="right" width="48">
        <x-slot name="trigger">
            <button type="button"
                    :aria-label="isDark ? '{{ __('app.theme.switch_light') }}' : '{{ __('app.theme.switch_dark') }}'"
                    class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg px-2 py-2 text-muted transition hover:text-ink">
                <svg x-show="!isDark" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1.5m0 15V21m9-9h-1.5m-15 0H3m15.4-6.4-1 1m-10.8 10.8-1 1m12.8 0-1-1M6.6 5.6l-1-1M16.5 12a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z" />
                </svg>
                <svg x-show="isDark" x-cloak class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z" />
                </svg>
            </button>
        </x-slot>

        <x-slot name="content">
            @foreach (['system' => __('app.theme.system'), 'light' => __('app.theme.light'), 'dark' => __('app.theme.dark')] as $value => $label)
                <button type="button"
                        @click="choose('{{ $value }}')"
                        :aria-current="preference === '{{ $value }}'"
                        class="flex w-full items-center justify-between px-4 py-2 text-start text-sm text-body transition hover:bg-surface-sunk"
                        :class="preference === '{{ $value }}' && 'font-semibold text-brand-ink'">
                    {{ $label }}
                    <svg x-show="preference === '{{ $value }}'" x-cloak class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7" />
                    </svg>
                </button>
            @endforeach
        </x-slot>
    </x-dropdown>
</div>
