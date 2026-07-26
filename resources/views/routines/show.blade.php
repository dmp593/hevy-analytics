<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800">{{ $routine->title }}</h2>
            <a href="{{ route('routines.edit', $routine->hevy_id) }}" class="text-sm text-indigo-600">Edit / stage progression →</a>
        </div>
    </x-slot>

    <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <x-flash />

        <div class="grid lg:grid-cols-2 gap-6 mb-6">
            <x-panel title="Per-session tonnage">
                <x-line-chart
                    :series="collect($sessionSeries)->map(fn($s) => ['label' => $s['date'], 'value' => $s['tonnage']])->all()"
                    label="Tonnage (kg)" color="#4f46e5"
                    empty="No sessions recorded for this routine yet." />
            </x-panel>

            <x-panel title="Exercise inside this routine" subtitle="e1RM progression for a chosen exercise">
                <form method="get" x-target="ex-chart" action="{{ route('routines.show', $routine->hevy_id) }}" class="mb-3 flex gap-2">
                    <select name="exercise" class="form-control" onchange="this.form.requestSubmit()">
                        <option value="">Select an exercise…</option>
                        @foreach($exercises as $e)
                            <option value="{{ $e['template_id'] }}" @selected($selectedExercise === $e['template_id'])>{{ $e['title'] }}</option>
                        @endforeach
                    </select>
                </form>
                <div id="ex-chart">
                    <x-line-chart :series="$e1rmSeries" label="e1RM (kg)" color="#16a34a" :fill="false"
                                  empty="Pick an exercise to chart its estimated 1RM within this routine." />
                </div>
            </x-panel>
        </div>

        <x-panel title="Muscle coverage" subtitle="Prescribed working sets per session vs weekly landmarks">
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($muscleCoverage as $m)
                    <div class="rounded-lg border border-gray-100 p-3">
                        <div class="flex justify-between text-sm">
                            <span class="capitalize font-medium">{{ str_replace('_',' ',$m['muscle']) }}</span>
                            <span>{{ $m['sets_per_session'] }} sets</span>
                        </div>
                        <div class="text-[10px] text-gray-400">weekly MEV {{ $m['landmarks']['mev'] }} · MAV {{ $m['landmarks']['mav'] }}</div>
                    </div>
                @endforeach
            </div>
        </x-panel>
    </div>
</x-app-layout>
