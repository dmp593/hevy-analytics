<x-ui.page :title="__('app.pages.performance')" :subtitle="__('app.pages.performance_sub')">
    <x-flash />

    {{-- Results first. Six filter controls used to be the first thing on the
         page; the answer now is, and the controls sit below it. --}}
    @include('performance._results')

    <details class="group mt-6 rounded-xl border border-line bg-surface">
        <summary class="cursor-pointer list-none px-5 py-3 text-sm font-medium text-muted marker:content-none">
            <span class="inline-flex items-center gap-2">
                <svg class="h-4 w-4 transition-transform group-open:rotate-90" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M7.5 5.5 12 10l-4.5 4.5V5.5z" />
                </svg>
                {{ __('app.performance.verdict.filters') }}
            </span>
        </summary>

        {{-- The id is load-bearing: the exercise picker in the e1RM card lives
             outside this element and joins the form via form="perf-filters". --}}
        <form id="perf-filters" x-target="perf-results" method="get" action="{{ route('performance.data') }}"
              class="grid grid-cols-2 items-end gap-3 border-t border-line px-5 py-4 md:grid-cols-4 lg:grid-cols-6">
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
            <label class="form-label">{{ __('app.nav.muscle') }}
                <select name="muscle" class="form-control">
                    <option value="">{{ __('app.common.all') }}</option>
                    @foreach ($muscles as $m)
                        <option value="{{ $m }}" @selected($filter->muscle === $m)>{{ \App\Support\Labels::muscle($m) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="form-label">{{ __('app.common.period') }}
                <select name="period" class="form-control">
                    @foreach (\App\Services\Analytics\PeriodService::PERIODS as $p)
                        <option value="{{ $p }}" @selected($filter->period === $p)>{{ __('app.periods.'.$p) }}</option>
                    @endforeach
                </select>
            </label>
            <x-ui.button type="submit">{{ __('app.common.apply') }}</x-ui.button>
        </form>
    </details>

    {{-- Triage over the same eight-week regressions the alerts use: every
         lift with a fitted trend, worst first. No second opinion — the
         thresholds are exerciseTrends()'s own, stated in the guide. --}}
    @if (count($statusBoard))
        <x-ui.card :title="__('app.board.title')" :subtitle="__('app.board.sub')" flush class="mt-6">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="table-head">
                            <th class="py-2 pl-5 pr-4 text-left">{{ __('app.board.exercise') }}</th>
                            <th class="py-2 pr-4 text-left">{{ __('app.board.trend') }}</th>
                            <th class="py-2 pr-4 text-right">{{ __('app.board.rate') }}</th>
                            <th class="py-2 pr-5 text-right">{{ __('app.board.sessions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($statusBoard as $row)
                            <tr class="table-row">
                                <td class="py-2 pl-5 pr-4">{{ $row['exercise'] }}</td>
                                <td class="py-2 pr-4">
                                    <span @class([
                                        'inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-semibold',
                                        'bg-good/10 text-good' => $row['direction'] === 'up',
                                        'bg-warn/10 text-warn' => $row['direction'] === 'flat',
                                        'bg-bad/10 text-bad' => $row['direction'] === 'down',
                                    ])>
                                        {{ ['up' => '↑', 'flat' => '→', 'down' => '↓'][$row['direction']] }}
                                        {{ __('app.board.'.$row['direction']) }}
                                    </span>
                                </td>
                                <td class="py-2 pr-4 text-right tabular-nums">{{ sprintf('%+.2f', $row['pct_per_week']) }}%/{{ __('app.mail.weekly.week_abbr') }}</td>
                                <td class="py-2 pr-5 text-right tabular-nums">{{ $row['sessions'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="px-5 py-3 text-xs text-muted">{{ __('app.board.explain') }}</p>
        </x-ui.card>
    @endif

    {{-- Your own data, run as the study: population papers say "evening is
         stronger on average" and "2-3 rest days"; these cards say what YOUR
         log says, sample sizes included. --}}
    @if ($recovery || $timeOfDay)
        <h2 class="mt-8 mb-3 text-xs font-semibold uppercase tracking-wide text-faint">{{ __('app.science.title') }}</h2>
        <div class="grid gap-6 lg:grid-cols-2">
            @if ($recovery)
                <x-ui.card :title="__('app.science.recovery_title')" :subtitle="__('app.science.recovery_sub', ['lifts' => implode(', ', $recovery['lifts'])])">
                    <ul class="space-y-2">
                        @foreach ($recovery['buckets'] as $b)
                            <li class="flex items-center justify-between gap-4 text-sm">
                                <span class="text-body">{{ trans_choice('app.science.rest_days', (int) $b['days'], ['days' => $b['days']]) }}</span>
                                <span class="tabular-nums {{ $b['mean_rel'] >= 0 ? 'text-good' : 'text-bad' }} font-semibold">
                                    {{ sprintf('%+.1f%%', $b['mean_rel']) }}
                                    <span class="font-normal text-xs text-faint">(n={{ $b['n'] }})</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-3 text-xs text-muted">{{ __('app.science.recovery_explain') }}</p>
                </x-ui.card>
            @endif

            @if ($timeOfDay)
                <x-ui.card :title="__('app.science.time_title')" :subtitle="trans_choice('app.science.time_sub', $timeOfDay['lifts'], ['lifts' => $timeOfDay['lifts']])">
                    <ul class="space-y-2">
                        @foreach ($timeOfDay['buckets'] as $name => $b)
                            <li class="flex items-center justify-between gap-4 text-sm">
                                <span class="text-body">{{ __('app.science.slot_'.$name) }}</span>
                                <span class="tabular-nums {{ $b['mean_rel'] >= 0 ? 'text-good' : 'text-bad' }} font-semibold">
                                    {{ sprintf('%+.1f%%', $b['mean_rel']) }}
                                    <span class="font-normal text-xs text-faint">(n={{ $b['n'] }})</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-3 text-xs text-muted">{{ __('app.science.time_explain') }}</p>
                </x-ui.card>
            @endif
        </div>
    @endif
</x-ui.page>