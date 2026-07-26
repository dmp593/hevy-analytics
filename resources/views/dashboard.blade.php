<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800">{{ __('app.dashboard.title') }}</h2>
            @unless($needsSetup ?? false)
                <span class="text-xs text-gray-500">
                    {{ __('app.dashboard.workouts', ['count' => $workoutCount]) }} ·
                    {{ __('app.sync.last_synced', ['when' => $lastSync ? $lastSync->diffForHumans() : __('app.sync.never')]) }}
                </span>
            @endunless
        </div>
    </x-slot>

    <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <x-flash />
        <x-sync-banner :status="$syncStatus ?? null" />

        @if($needsSetup ?? false)
            <x-panel :title="__('app.dashboard.welcome_title')">
                <p class="text-sm text-gray-600 mb-4">{{ __('app.dashboard.welcome_body') }}</p>
                <a href="{{ route('profile.edit') }}" class="btn-primary">{{ __('app.dashboard.set_up_profile') }}</a>
            </x-panel>
        @else
            @php
                $goalLabel = $goal ? \App\Science\Goals\GoalType::label($goal->type) : __('app.dashboard.no_goal');
                $ratePct = $rate['pct_bw_per_week'] ?? null;
            @endphp

            {{-- Alerts --}}
            <div class="grid gap-3 mb-6">
                @foreach($alerts as $a)
                    <x-alert :level="$a['level']" :title="$a['title']">{{ $a['message'] }}</x-alert>
                @endforeach
            </div>

            {{-- KPI cards --}}
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
                <x-stat :label="__('app.dashboard.goal')" :value="$goalLabel" tone="accent" />
                <x-stat :label="__('app.dashboard.weight')" :value="$status['weight_kg'] ?? '—'" unit="kg"
                        :sub="$ratePct !== null ? __('app.units.bw_per_week', ['value' => sprintf('%+.2f', $ratePct)]) : null"
                        tip="Your bodyweight and how fast it's changing per week. Lean-bulk sweet spot: +0.25% to +0.5% of bodyweight per week." />
                <x-stat :label="__('app.dashboard.body_fat')" :value="$status['fat_percent'] ?? '—'" unit="%"
                        :sub="$status['navy_fat_percent'] ? 'Navy: '.$status['navy_fat_percent'].'%' : null"
                        tip="Percent of your weight that is fat. Navy = an independent tape-measure estimate used as a cross-check." />
                <x-stat :label="__('app.dashboard.lean_mass')" :value="$status['lean_mass_kg'] ?? '—'" unit="kg"
                        :sub="$status['ffmi_normalized'] ? 'FFMI '.$status['ffmi_normalized'] : null"
                        tip="Everything that isn't fat (mostly muscle). FFMI = muscularity adjusted for height; ~25 is near the natural ceiling for men." />
                <x-stat :label="__('app.dashboard.volume_4wk')" :value="number_format($weekVolume)" unit="kg"
                        tip="Tonnage = weight × reps summed over the last 4 weeks. Rising over time = progressive overload." />
                <x-stat :label="__('app.dashboard.hard_sets_4wk')" :value="$weekSets"
                        tip="Total working sets (warm-ups excluded) in the last 4 weeks." />
            </div>

            <div class="grid lg:grid-cols-2 gap-6 mb-6">
                <x-panel :title="__('app.dashboard.training_volume')" :subtitle="__('app.dashboard.training_volume_sub')">
                    <x-line-chart :series="$tonnageSeries" :label="__('app.series.tonnage')" color="#4f46e5" />
                </x-panel>

                <x-panel :title="__('app.dashboard.body_composition')" :subtitle="__('app.dashboard.body_composition_sub')">
                    <x-multi-line-chart :sets="[
                        ['series' => $weightSeries, 'label' => __('app.series.weight'), 'color' => '#0ea5e9'],
                        ['series' => $leanSeries, 'label' => __('app.series.lean_mass'), 'color' => '#16a34a'],
                    ]" :empty="__('app.chart.no_body_data')" />
                </x-panel>
            </div>

            <div class="grid lg:grid-cols-3 gap-6">
                <x-panel :title="__('app.dashboard.weekly_sets')" :subtitle="__('app.dashboard.weekly_sets_sub')" class="lg:col-span-2">
                    <x-slot name="actions">
                        <x-info title="Weekly hard sets" text="Hard working sets per muscle each week — the main driver of growth. MEV = minimum to grow, MAV = best-return zone. Aim between them. 'Below maintenance' means too few sets to grow." />
                    </x-slot>
                    <x-muscle-volume-bars :items="$weeklySetsPerMuscle" :limit="10" :show-landmarks="false" />
                </x-panel>

                <x-panel :title="__('app.dashboard.muscle_balance')" :subtitle="__('app.dashboard.muscle_balance_sub')">
                    <x-balance-ratios :items="$balance" />
                </x-panel>
            </div>

            @if($nutrition)
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mt-6">
                    <x-stat :label="__('app.nutrition.maintenance')" :value="number_format($nutrition->tdee)" unit="kcal" tone="default"
                            tip="Total calories you burn per day. Eat this to maintain weight." />
                    <x-stat :label="__('app.nutrition.target_calories')" :value="number_format($nutrition->target_calories)" unit="kcal" tone="accent"
                            tip="Maintenance adjusted for your goal (e.g. a small surplus for a lean bulk)." />
                    <x-stat :label="__('app.nutrition.protein')" :value="$nutrition->protein_g" unit="g" tone="good"
                            tip="Builds and protects muscle. Target ~1.6–2.2 g per kg bodyweight." />
                    <x-stat :label="__('app.nutrition.fat')" :value="$nutrition->fat_g" unit="g"
                            tip="Supports hormones. Keep at least ~0.5 g per kg bodyweight." />
                    <x-stat :label="__('app.nutrition.carbs')" :value="$nutrition->carb_g" unit="g"
                            tip="Fuel for training; fills the remaining calories after protein and fat." />
                </div>
            @endif
        @endif
    </div>
</x-app-layout>
