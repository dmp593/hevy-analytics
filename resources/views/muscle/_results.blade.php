{{-- Swapped in by Alpine AJAX when the filter changes, so the verdict has to
     live inside this fragment: it is derived from the filtered window and would
     otherwise keep stating a conclusion about the previous date range. --}}
<div id="muscle-results" class="space-y-6">
    @if ($verdict)
        <x-ui.insight :tone="$verdict['tone']" :title="$verdict['headline']">
            <p>{{ $verdict['body'] }}</p>

            @if ($verdict['imbalance'])
                <p class="mt-2">{{ $verdict['imbalance'] }}</p>
            @endif
        </x-ui.insight>
    @endif

    @if ($verdict && $verdict['gaps'])
        {{-- The shortfalls on their own, largest first. This is the part an
             athlete acts on, so it gets its own block rather than being
             something to work out from the full table below. --}}
        <x-ui.card :title="__('app.muscle.verdict.gaps_title')">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($verdict['gaps'] as $gap)
                    <div class="flex items-baseline justify-between gap-3 rounded-lg border border-line bg-surface-sunk px-3 py-2">
                        <div class="min-w-0">
                            <div class="truncate text-sm font-medium text-ink">{{ $gap['muscle'] }}</div>
                            <div class="text-xs text-muted">
                                {{ __('app.muscle.verdict.per_week_now', [
                                    'current' => $gap['current'],
                                    'target' => $gap['target'],
                                ]) }}
                            </div>
                        </div>
                        <div class="shrink-0 text-sm font-semibold text-warn">
                            {{ __('app.muscle.verdict.add_sets', ['count' => (int) $gap['need']]) }}
                        </div>
                    </div>
                @endforeach
            </div>
        </x-ui.card>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card :title="__('app.muscle.verdict.full_breakdown')" :subtitle="__('app.dashboard.weekly_sets_sub')">
            <x-slot:actions>
                <x-info title="MV · MEV · MAV · MRV" :text="__('app.tips.landmarks')" />
            </x-slot:actions>
            <x-muscle-volume-bars :items="$weeklySetsPerMuscle" />
        </x-ui.card>

        <div class="space-y-6">
            <x-ui.card :title="__('app.muscle.verdict.balance')" :subtitle="__('app.dashboard.muscle_balance_sub')">
                <x-balance-ratios :items="$balance" />
            </x-ui.card>

            <x-ui.card :title="__('app.muscle.verdict.distribution')" :subtitle="__('app.muscle.verdict.distribution_sub')">
                @if (count($volumePerMuscle))
                    <x-chart type="polarArea" :height="280"
                        :labels="array_map(fn ($x) => \App\Support\Labels::muscle($x['muscle']), $volumePerMuscle)"
                        :datasets="[['label' => __('app.series.tonnage'), 'data' => array_column($volumePerMuscle, 'tonnage')]]" />
                @else
                    <x-empty-chart :height="280">{{ __('app.chart.no_sets') }}</x-empty-chart>
                @endif
            </x-ui.card>
        </div>
    </div>
</div>
