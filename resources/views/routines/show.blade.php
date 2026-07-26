<x-ui.page :title="$routine->title">
    <x-slot:actions>
        <x-ui.button :href="route('routines.edit', $routine->hevy_id)" variant="secondary" size="sm">
            {{ __('app.routines.edit_progression') }}
        </x-ui.button>
    </x-slot:actions>

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
                <div class="rounded-lg border border-subtle p-3">
                    <div class="flex justify-between text-sm">
                        <span class="capitalize font-medium">{{ str_replace('_',' ',$m['muscle']) }}</span>
                        <span>{{ $m['sets_per_session'] }} sets</span>
                    </div>
                    <div class="text-[10px] text-faint">weekly MEV {{ $m['landmarks']['mev'] }} · MAV {{ $m['landmarks']['mav'] }}</div>
                </div>
            @endforeach
        </div>
    </x-panel>
</x-ui.page>
