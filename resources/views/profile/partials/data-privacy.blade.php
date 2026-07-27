<x-ui.card :title="__('app.settings.data.title')" :subtitle="__('app.settings.data.subtitle')">
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-line bg-surface-sunk px-4 py-3">
            <div class="min-w-0">
                <div class="text-sm font-medium text-ink">{{ __('app.settings.data.export') }}</div>
                <p class="mt-0.5 text-xs text-muted">{{ __('app.settings.data.export_help') }}</p>
            </div>
            <x-ui.button :href="route('settings.export')" variant="secondary" size="sm">
                {{ __('app.settings.data.download') }}
            </x-ui.button>
        </div>

        <p class="text-xs text-muted">{{ __('app.settings.data.third_parties') }}</p>
    </div>
</x-ui.card>
