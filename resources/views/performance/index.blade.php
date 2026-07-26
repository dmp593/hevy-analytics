<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-ink">Performance over time</h2></x-slot>

    <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <x-flash />

        <x-panel class="mb-6">
            <form x-target="perf-results" method="get" action="{{ route('performance.data') }}"
                  class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3 items-end">
                <label class="form-label">From
                    <input type="date" name="from" value="{{ $filter->from?->toDateString() }}" class="form-control">
                </label>
                <label class="form-label">To
                    <input type="date" name="to" value="{{ $filter->to?->toDateString() }}" class="form-control">
                </label>
                <label class="form-label">Routine
                    <select name="routine" class="form-control">
                        <option value="">All</option>
                        @foreach($routines as $r)
                            <option value="{{ $r->hevy_id }}" @selected($filter->routineHevyId === $r->hevy_id)>{{ $r->title }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-label">Exercise
                    <select name="exercise" class="form-control">
                        <option value="">All</option>
                        @foreach($exercises as $e)
                            <option value="{{ $e->hevy_id }}" @selected($filter->exerciseTemplateHevyId === $e->hevy_id)>{{ $e->title }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-label">Muscle
                    <select name="muscle" class="form-control">
                        <option value="">All</option>
                        @foreach($muscles as $m)
                            <option value="{{ $m }}" @selected($filter->muscle === $m)>{{ ucfirst(str_replace('_',' ',$m)) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-label">Period
                    <select name="period" class="form-control">
                        @foreach(\App\Services\Analytics\PeriodService::PERIODS as $p)
                            <option value="{{ $p }}" @selected($filter->period === $p)>{{ ucfirst($p) }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="btn-primary">Apply</button>
            </form>
        </x-panel>

        @include('performance._results')
    </div>
</x-app-layout>
