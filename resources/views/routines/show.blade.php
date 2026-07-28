<x-ui.page :title="$routine->title">
    <x-slot:actions>
        <x-ui.button :href="route('routines.edit', $routine->hevy_id)" variant="secondary" size="sm">
            {{ __('app.routines.edit_progression') }}
        </x-ui.button>
    </x-slot:actions>

    <x-flash />

    <div class="grid lg:grid-cols-2 gap-6 mb-6">
        <x-panel :title="__('app.routines.session_tonnage')">
            <x-line-chart
                :series="units()->weightSeries(collect($sessionSeries)->map(fn($s) => ['label' => $s['date'], 'value' => $s['tonnage']])->all())"
                :label="__('app.series.tonnage', ['unit' => units()->weightUnit()])" :color="\App\Support\Chart::series(0)"
                :empty="__('app.routines.no_sessions')" />
        </x-panel>

        <x-panel :title="__('app.routines.exercise_in_routine')" :subtitle="__('app.routines.exercise_in_routine_sub')">
            <form method="get" x-target="ex-chart" action="{{ route('routines.show', $routine->hevy_id) }}" class="mb-3 flex gap-2">
                <select name="exercise" class="form-control" onchange="this.form.requestSubmit()">
                    <option value="">{{ __('app.routines.select_exercise') }}</option>
                    @foreach($exercises as $e)
                        <option value="{{ $e['template_id'] }}" @selected($selectedExercise === $e['template_id'])>{{ $e['title'] }}</option>
                    @endforeach
                </select>
            </form>
            <div id="ex-chart">
                <x-line-chart :series="units()->weightSeries($e1rmSeries)" :label="__('app.series.e1rm', ['unit' => units()->weightUnit()])" :color="\App\Support\Chart::series(2)" :fill="false"
                              :empty="__('app.routines.pick_exercise')" />
            </div>
        </x-panel>
    </div>

    <x-panel :title="__('app.routines.coverage')" :subtitle="__('app.routines.coverage_sub')">
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach($muscleCoverage as $m)
                <div class="rounded-lg border border-subtle p-3">
                    <div class="flex justify-between text-sm">
                        <span class="font-medium">{{ \App\Support\Labels::muscle($m['muscle']) }}</span>
                        <span>{{ __('app.routines.sets_per_session', ['count' => $m['sets_per_session']]) }}</span>
                    </div>
                    <div class="text-[11px] text-faint">{{ __('app.routines.weekly_landmarks', ['mev' => $m['landmarks']['mev'], 'mav' => $m['landmarks']['mav']]) }}</div>
                </div>
            @endforeach
        </div>
    </x-panel>
</x-ui.page>
