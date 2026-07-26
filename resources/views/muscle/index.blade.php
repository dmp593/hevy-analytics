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
</x-ui.page>
