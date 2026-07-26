<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-ink">Muscle analysis</h2></x-slot>

    <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <x-flash />

        <x-panel class="mb-6">
            <form x-target="muscle-results" method="get" action="{{ route('muscle.data') }}"
                  class="grid grid-cols-2 md:grid-cols-4 gap-3 items-end">
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
                <button class="btn-primary">Apply</button>
            </form>
        </x-panel>

        @include('muscle._results')
    </div>
</x-app-layout>
