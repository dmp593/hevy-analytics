{{-- Swapped in by Alpine AJAX, so everything derived from the filter lives in
     here — a verdict left in the page shell would keep describing the previous
     window after the athlete narrowed it. --}}
<div id="perf-results" class="space-y-6">
    @if ($verdict)
        <x-ui.insight :tone="$verdict['tone']" :title="$verdict['headline']">
            <p>{{ $verdict['body'] }}</p>

            @if ($verdict['unknown'] > 0)
                <p class="mt-2 text-xs text-faint">
                    {{ trans_choice('app.performance.verdict.excluded', $verdict['unknown'], ['count' => $verdict['unknown']]) }}
                </p>
            @endif
        </x-ui.insight>
    @endif

    @if (! empty($strengthLevel))
        <x-ui.card :title="__('app.pages.levels')">
            <x-slot:actions>
                <x-info :title="__('app.pages.levels')" :text="__('app.tips.strength_level')" />
            </x-slot:actions>
            <x-strength-bar :eval="$strengthLevel" />
        </x-ui.card>
    @endif

    {{-- Lift by lift: the direction of each estimated 1RM, steepest first. This
         is the evidence for the verdict above and the part worth scanning. --}}
    @if ($verdict && $verdict['movers'])
        <x-ui.card :title="__('app.performance.verdict.movers')" :subtitle="__('app.performance.verdict.movers_sub')">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($verdict['movers'] as $mover)
                    <div class="flex items-baseline justify-between gap-3 rounded-lg border border-line bg-surface-sunk px-3 py-2">
                        <span class="min-w-0 truncate text-sm font-medium text-ink">{{ $mover['exercise'] }}</span>
                        <span @class([
                            'shrink-0 text-sm font-semibold',
                            'text-good' => $mover['direction'] === 'up',
                            'text-bad' => $mover['direction'] === 'down',
                            'text-muted' => $mover['direction'] === 'flat',
                        ])>
                            {{ __('app.performance.verdict.per_year', ['value' => sprintf('%+.1f kg', $mover['per_week'] * 52)]) }}
                        </span>
                    </div>
                @endforeach
            </div>
        </x-ui.card>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card :title="__('app.series.e1rm')"
                   :subtitle="$filter->exerciseTemplateHevyId ? __('app.performance.e1rm_sub') : __('app.performance.pick_exercise')">
            {{-- Bound to the filter form by id rather than nested inside it.

                 Choosing an exercise is the main reason anyone touches the
                 filters on this page, so hiding it in the disclosure at the
                 bottom left the empty chart pointing at a control the athlete
                 could not see. The `form` attribute lets this control belong to
                 that form while living here, so there is still one form and one
                 source of filter state. --}}
            <x-slot:actions>
                <select name="exercise" form="perf-filters" class="form-control mt-0 w-48 text-xs"
                        onchange="this.form.requestSubmit()" aria-label="{{ __('app.performance.exercise') }}">
                    <option value="">{{ __('app.common.all') }}</option>
                    @foreach ($exercises as $e)
                        <option value="{{ $e->hevy_id }}" @selected($filter->exerciseTemplateHevyId === $e->hevy_id)>{{ $e->title }}</option>
                    @endforeach
                </select>
            </x-slot:actions>

            <x-line-chart :series="$e1rmSeries" :label="__('app.series.e1rm')" :color="\App\Support\Chart::series(2)"
                          :empty="__('app.performance.pick_exercise_empty')" />
        </x-ui.card>

        <x-ui.card :title="__('app.performance.verdict.volume')" :subtitle="__('app.performance.verdict.volume_sub')">
            <x-line-chart :series="$tonnageSeries" :label="__('app.series.tonnage')" :color="\App\Support\Chart::series(0)"
                          :empty="__('app.chart.no_data')" />
            {{-- The newest bucket is almost always a period still in progress, so
                 it renders as a cliff. Saying so beats letting the athlete read a
                 partial month as a collapse in training. --}}
            <p class="mt-2 text-xs text-faint">{{ __('app.performance.verdict.partial_period') }}</p>
        </x-ui.card>
    </div>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-ui.stat :label="__('app.performance.tonnage')" :value="number_format($tonnage)" unit="kg" />
        <x-ui.stat :label="__('app.performance.working_sets')" :value="number_format($totalSets)" />
        <x-ui.stat :label="__('app.performance.total_reps')" :value="number_format($totalReps)" />
        @if ($strengthScores)
            <x-ui.stat :label="__('app.performance.wilks')" :value="$strengthScores['wilks'] ?? '—'"
                       :sub="'DOTS '.($strengthScores['dots'] ?? '—')" />
        @else
            <x-ui.stat :label="__('app.performance.exercises')" :value="count($prs)" />
        @endif
    </div>

    <x-ui.card :title="__('app.performance.verdict.records')" :subtitle="__('app.performance.verdict.records_sub')" flush>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="table-head">
                        <th class="py-2 pl-5 pr-4">{{ __('app.performance.exercise') }}</th>
                        <th class="py-2 pr-4">{{ __('app.performance.verdict.trend') }}</th>
                        <th class="py-2 pr-4">{{ __('app.performance.best_e1rm') }}</th>
                        <th class="py-2 pr-4">{{ __('app.performance.top_weight') }}</th>
                        <th class="py-2 pr-5 pr-4">{{ __('app.performance.best_set') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (array_slice($prs, 0, 25) as $p)
                        @php $trend = $trends[$p['template_id'] ?? $p['exercise']] ?? null; @endphp
                        <tr class="table-row">
                            <td class="py-2 pl-5 pr-4">
                                <div class="font-medium text-ink">{{ $p['exercise'] }}</div>
                                <div class="text-xs text-muted">{{ \App\Support\Labels::muscle($p['muscle'] ?? '') }}</div>
                            </td>
                            <td class="py-2 pr-4">
                                @if ($trend && $trend['reliable'])
                                    <span @class([
                                        'font-semibold',
                                        'text-good' => $trend['direction'] === 'up',
                                        'text-bad' => $trend['direction'] === 'down',
                                        'text-muted' => $trend['direction'] === 'flat',
                                    ])>{{ sprintf('%+.1f kg', $trend['per_week'] * 52) }}</span>
                                    <span class="text-xs text-faint">/yr</span>
                                @elseif ($trend)
                                    <span class="text-xs text-muted" title="{{ __('app.performance.verdict.noisy_tip') }}">
                                        {{ __('app.performance.verdict.noisy') }}
                                    </span>
                                @else
                                    <span class="text-xs text-faint">{{ __('app.performance.verdict.not_enough') }}</span>
                                @endif
                            </td>
                            <td class="py-2 pr-4">{{ $p['best_e1rm'] ? round($p['best_e1rm'], 1).' kg' : '—' }}</td>
                            <td class="py-2 pr-4">{{ $p['best_weight'] ? round($p['best_weight'], 1).' kg' : '—' }}</td>
                            <td class="py-2 pr-5">{{ $p['best_volume_set'] ? number_format($p['best_volume_set']).' kg' : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-3 text-muted">{{ __('app.performance.no_exercises') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>
</div>
