<x-ui.page :title="__('app.import.title')" :subtitle="__('app.import.subtitle')" width="3xl" class="space-y-6">
    <x-flash />

    <x-ui.card :title="__('app.import.how_title')">
        <p class="text-sm text-body">{{ __('app.import.formats_body') }}</p>
        <p class="mt-2 text-sm text-body">
            {!! __('app.import.convert_link', [
                'convert' => '<a href="'.route('convert').'" class="underline">'.__('app.convert.open').'</a>',
            ]) !!}
        </p>

        {{-- Per-app export paths, folded: most people only need their own. --}}
        <div class="mt-3 space-y-1">
            @foreach (['hevy', 'strong', 'fitnotes', 'jefit'] as $app)
                <details class="group rounded-lg border border-subtle">
                    <summary class="flex min-h-11 cursor-pointer list-none items-center gap-2 px-3 text-sm font-medium text-ink marker:content-none">
                        <span class="inline-block text-muted transition group-open:rotate-90" aria-hidden="true">›</span>
                        {{ __('app.import.source_'.$app) }}
                    </summary>
                    <p class="px-3 pb-3 text-sm text-body">{{ __('app.import.instructions.'.$app) }}</p>
                </details>
            @endforeach
        </div>

        <form method="POST" action="{{ route('import.store') }}" enctype="multipart/form-data" class="mt-6 space-y-4">
            @csrf
            <div class="flex flex-wrap items-center gap-3">
                <input type="file" name="file" accept=".csv,text/csv" required
                       class="form-file">
            </div>

            <div>
                <label class="text-xs text-body block">{{ __('app.import.unit_label') }}
                    <select name="unit" class="mt-1 block w-40 rounded-md border-line text-sm">
                        <option value="kg" @selected($defaultUnit === 'kg')>kg</option>
                        <option value="lbs" @selected($defaultUnit === 'lbs')>lb</option>
                    </select>
                </label>
                <p class="mt-1 text-xs text-muted">{{ __('app.import.unit_help') }}</p>
            </div>

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
