<x-ui.page :title="__('app.pages.muscle')" :subtitle="__('app.pages.muscle_sub')">
    <x-flash />

    {{-- Results first, filter second.

         The filter used to open the page, which meant the first thing an athlete
         saw was a form rather than the answer. Almost nobody changes the default
         window, so it sits in a collapsed <details> — available, not in the way. --}}
    @include('muscle._results')

    <details class="group mt-6 rounded-xl border border-line bg-surface">
        <summary class="cursor-pointer list-none px-5 py-3 text-sm font-medium text-muted marker:content-none">
            <span class="inline-flex items-center gap-2">
                <svg class="h-4 w-4 transition-transform group-open:rotate-90" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M7.5 5.5 12 10l-4.5 4.5V5.5z" />
                </svg>
                {{ __('app.muscle.verdict.filters') }}
            </span>
        </summary>

        <form x-target="muscle-results" method="get" action="{{ route('muscle.data') }}"
              class="grid grid-cols-2 items-end gap-3 border-t border-line px-5 py-4 md:grid-cols-4">
            <label class="form-label">{{ __('app.common.from') }}
                <input type="date" name="from" value="{{ $filter->from?->toDateString() }}" class="form-control">
            </label>
            <label class="form-label">{{ __('app.common.to') }}
                <input type="date" name="to" value="{{ $filter->to?->toDateString() }}" class="form-control">
            </label>
            <label class="form-label">{{ __('app.nav.routines') }}
                <select name="routine" class="form-control">
                    <option value="">{{ __('app.common.all') }}</option>
                    @foreach ($routines as $r)
                        <option value="{{ $r->hevy_id }}" @selected($filter->routineHevyId === $r->hevy_id)>{{ $r->title }}</option>
                    @endforeach
                </select>
            </label>
            <x-ui.button type="submit">{{ __('app.common.apply') }}</x-ui.button>
        </form>
    </details>

    {{-- Effort, from logged RPE — always the last 28 days, unaffected by the
         filter above. Silent unless enough sets carry an RPE: guessing what
         unlogged effort felt like would be invention, not analysis. --}}
    @if ($effort['total_sets'] > 0)
        <x-ui.card :title="__('app.effort.title')" :subtitle="__('app.effort.sub')" class="mt-6">
            @if (! $effort['enough'])
                <p class="text-sm text-muted">{{ __('app.effort.low_coverage', ['pct' => $effort['coverage_pct']]) }}</p>
            @elseif (empty($effort['flagged']))
                <p class="text-sm text-body">{{ __('app.effort.all_close') }}</p>
                <p class="mt-2 text-xs text-muted">{{ __('app.effort.explain') }}</p>
            @else
                <ul class="space-y-2">
                    @foreach ($effort['flagged'] as $f)
                        <li class="flex items-center justify-between gap-4 text-sm">
                            <span class="text-ink">{{ \App\Support\Labels::muscle($f['muscle']) }}</span>
                            <span class="text-muted">{{ __('app.effort.far_share', ['pct' => $f['far_pct'], 'sets' => $f['sets']]) }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-3 text-xs text-muted">{{ __('app.effort.explain') }}</p>
                @if (auth()->user()->activeGoal()?->type === 'strength')
                    <p class="mt-1 text-xs text-faint">{{ __('app.effort.strength_caveat') }}</p>
                @endif
            @endif
        </x-ui.card>
    @endif

    {{-- Progressive overload per muscle: the set-weighted mean of each
         lift's e1RM slope over eight weeks — the transparent version of a
         "progressive overload index". The formula is in the tooltip. --}}
    @if (count($overload))
        <x-ui.card :title="__('app.overload.title')" :subtitle="__('app.overload.sub')" class="mt-6">
            <ul class="space-y-2">
                @foreach ($overload as $m)
                    <li class="flex items-center justify-between gap-4 text-sm">
                        <span class="text-ink">{{ \App\Support\Labels::muscle($m['muscle']) }}
                            <span class="text-xs text-faint">· {{ trans_choice('app.overload.lifts', $m['lifts'], ['count' => $m['lifts']]) }}</span>
                        </span>
                        <span @class([
                            'tabular-nums font-semibold',
                            'text-good' => $m['direction'] === 'up',
                            'text-muted' => $m['direction'] === 'flat',
                            'text-bad' => $m['direction'] === 'down',
                        ])>{{ sprintf('%+.2f', $m['pct_per_week']) }}%/{{ __('app.mail.weekly.week_abbr') }}</span>
                    </li>
                @endforeach
            </ul>
            <p class="mt-3 text-xs text-muted">{{ __('app.overload.explain') }}</p>
        </x-ui.card>
    @endif
</x-ui.page>