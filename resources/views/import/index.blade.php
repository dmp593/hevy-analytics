<x-ui.page :title="__('app.import.title')" :subtitle="__('app.import.subtitle')" width="3xl" class="space-y-6">
    <x-flash />

    <x-ui.card :title="__('app.import.how_title')">
        <ol class="list-inside list-decimal space-y-2 text-sm text-body">
            <li>{{ __('app.import.step_export') }}</li>
            <li>{{ __('app.import.step_email') }}</li>
            <li>{{ __('app.import.step_upload') }}</li>
        </ol>

        <form method="POST" action="{{ route('import.store') }}" enctype="multipart/form-data" class="mt-6 flex flex-wrap items-center gap-3">
            @csrf
            <input type="file" name="file" accept=".csv,text/csv" required
                   class="text-sm text-body file:mr-3 file:min-h-11 file:cursor-pointer file:rounded-lg file:border-0 file:bg-surface-sunk file:px-4 file:py-2 file:text-sm file:font-semibold file:text-ink hover:file:bg-strong/40">
            <x-ui.button type="submit">{{ __('app.import.submit') }}</x-ui.button>
        </form>
        <x-input-error class="mt-2" :messages="$errors->get('file')" />

        {{-- Safe to repeat, and worth saying: fear of duplicates is the reason
             people hesitate to re-upload a fresh export. --}}
        <p class="mt-4 text-xs text-muted">{{ __('app.import.idempotent') }}</p>
    </x-ui.card>

    <x-ui.card :title="__('app.import.what_title')">
        <ul class="list-inside list-disc space-y-1.5 text-sm text-body">
            <li>{{ __('app.import.covers') }}</li>
            <li>{{ __('app.import.muscles') }}</li>
            <li>{{ __('app.import.no_body') }}</li>
        </ul>

        @if ($workoutCount > 0)
            <p class="mt-4 text-sm text-muted">
                {{ trans_choice('app.import.existing', $workoutCount, ['count' => number_format($workoutCount)]) }}
            </p>
        @endif
    </x-ui.card>
</x-ui.page>
