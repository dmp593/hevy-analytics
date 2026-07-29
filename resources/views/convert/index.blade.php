<x-ui.page :title="__('app.convert.title')" :subtitle="__('app.convert.subtitle')" width="3xl" class="space-y-6">
    <x-flash />

    <x-ui.card :title="__('app.convert.pick_title')">
        <form method="POST" action="{{ route('convert.preview') }}" enctype="multipart/form-data" class="space-y-4"
              x-data="{ mode: '{{ $input['mode'] ?? 'account' }}' }">
            @csrf

            {{-- Source: the account's history, or a file that never touches it. --}}
            <div class="flex flex-wrap gap-2">
                @foreach (['account', 'file'] as $mode)
                    <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-lg border px-3 text-sm transition"
                           :class="mode === '{{ $mode }}' ? 'border-brand bg-brand-soft text-brand-ink font-semibold' : 'border-line text-body hover:bg-surface-sunk'">
                        <input type="radio" name="mode" value="{{ $mode }}" x-model="mode" class="sr-only">
                        {{ __('app.convert.mode_'.$mode) }}
                    </label>
                @endforeach
            </div>

            <template x-if="mode === 'account'">
                <div class="space-y-3">
                    <p class="text-xs text-muted">{{ trans_choice('app.convert.account_help', $workoutCount, ['count' => number_format($workoutCount)]) }}</p>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="text-xs text-body block">{{ __('app.convert.date_from') }}
                            <input type="date" name="from" value="{{ $input['from'] ?? '' }}" class="mt-1 w-full rounded-md border-line text-sm">
                        </label>
                        <label class="text-xs text-body block">{{ __('app.convert.date_to') }}
                            <input type="date" name="to" value="{{ $input['to'] ?? '' }}" class="mt-1 w-full rounded-md border-line text-sm">
                        </label>
                    </div>
                </div>
            </template>

            <template x-if="mode === 'file'">
                <div class="space-y-3">
                    <p class="text-xs text-muted">{{ __('app.convert.file_help') }}</p>
                    <input type="file" name="file" accept=".csv,text/csv"
                           class="form-file">
                    <div>
                        <label class="text-xs text-body block">{{ __('app.import.unit_label') }}
                            <select name="unit" class="mt-1 block w-40 rounded-md border-line text-sm">
                                <option value="kg" @selected(($input['unit'] ?? 'kg') === 'kg')>kg</option>
                                <option value="lbs" @selected(($input['unit'] ?? '') === 'lbs')>lb</option>
                            </select>
                        </label>
                        <p class="mt-1 text-xs text-muted">{{ __('app.import.unit_help') }}</p>
                    </div>
                </div>
            </template>

            <div>
                <label class="text-xs text-body block">{{ __('app.convert.target_label') }}
                    <select name="target" class="mt-1 block w-full sm:w-64 rounded-md border-line text-sm">
                        @foreach ($targets as $target)
                            <option value="{{ $target }}" @selected(($input['target'] ?? '') === $target)>
                                {{ __('app.import.source_'.$target) }}@if ($target === 'jefit') · {{ __('app.convert.beta') }}@endif
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>

            <x-input-error class="mt-2" :messages="$errors->all()" />
            <x-ui.button type="submit">{{ __('app.convert.preview_cta') }}</x-ui.button>
        </form>

        <p class="mt-4 text-xs text-muted">{{ __('app.convert.names_note') }}</p>
        <p class="mt-1 text-xs text-muted">{{ __('app.convert.kg_note') }}</p>
    </x-ui.card>

    @if ($preview)
        <x-ui.card :title="__('app.convert.result_title')">
            <p class="text-sm text-body">
                {{ __('app.convert.summary', [
                    'workouts' => number_format($preview['workouts']),
                    'sets' => number_format($preview['sets']),
                    'source' => $preview['source'] === 'account'
                        ? __('app.convert.source_account')
                        : (in_array($preview['source'], ['generic', 'mapped'], true) ? 'CSV' : __('app.import.source_'.$preview['source'])),
                ]) }}
            </p>

            {{-- The loss manifest: counted from these rows, not boilerplate. --}}
            @if (count($preview['losses']))
                <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-faint">{{ __('app.convert.losses_title') }}</p>
                <ul class="mt-2 space-y-1">
                    @foreach ($preview['losses'] as $loss)
                        <li class="flex items-start gap-2 text-sm text-body">
                            <span class="mt-0.5 text-warn" aria-hidden="true">▲</span>
                            {{ trans_choice('app.convert.losses.'.$loss['key'], $loss['count'], ['count' => number_format($loss['count'])]) }}
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-3 text-sm text-good">{{ __('app.convert.nothing_lost') }}</p>
            @endif

            @if (($input['target'] ?? '') === 'jefit')
                <p class="mt-3 text-xs text-warn">{{ __('app.convert.jefit_beta') }}</p>
            @endif

            @if ($entitled)
                <form method="POST" action="{{ route('convert.download') }}" class="mt-5">
                    @csrf
                    @foreach (['mode', 'target', 'unit', 'from', 'to'] as $field)
                        @if (filled($input[$field] ?? null))
                            <input type="hidden" name="{{ $field }}" value="{{ $input[$field] }}">
                        @endif
                    @endforeach
                    <x-ui.button type="submit">{{ __('app.convert.download') }}</x-ui.button>
                </form>
            @else
                {{-- The preview told the truth for free; the file is the product. --}}
                <div class="mt-5 rounded-lg border border-brand bg-brand-soft p-4">
                    <p class="text-sm font-semibold text-brand-ink">{{ __('app.convert.paywall_title') }}</p>
                    <p class="mt-1 text-sm text-brand-ink">{{ __('app.convert.paywall_body') }}</p>
                    <x-ui.button :href="route('billing')" class="mt-3">{{ __('app.convert.paywall_cta') }}</x-ui.button>
                </div>
            @endif
        </x-ui.card>
    @endif
</x-ui.page>
