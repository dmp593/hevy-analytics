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
</x-ui.page>
