<x-ui.page :title="__('app.pages.body')" :subtitle="__('app.pages.body_sub')">
    <x-flash />

    {{-- The verdict leads.

         Everything below it is evidence. Previously the page opened with six
         numbers and closed, bottom-right, with a fixed sentence asserting a
         conclusion the numbers might contradict. --}}
    @if ($verdict)
        <x-ui.insight :tone="$verdict['tone']" :title="$verdict['headline']" class="mb-6">
            <p>{{ $verdict['body'] }}</p>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                @foreach ($verdict['signals'] as $signal)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-surface-sunk px-2.5 py-1 text-xs">
                        <span class="text-muted">{{ $signal['label'] }}</span>
                        <span @class([
                            'font-semibold',
                            'text-good' => $signal['direction'] === 'good',
                            'text-bad' => $signal['direction'] === 'bad',
                            'text-muted' => in_array($signal['direction'], ['flat', 'neutral'], true),
                        ])>{{ $signal['detail'] }}</span>
                    </span>
                @endforeach
            </div>

            <p class="mt-2 text-xs text-faint">
                {{ __('app.body.verdict.confidence_'.$verdict['confidence'], ['count' => count($verdict['signals'])]) }}
            </p>
        </x-ui.insight>
    @endif

    {{-- Four tiles, not six. FFMI and waist/height moved into the detail
         section below: they are reference numbers you look up, not numbers you
         check every visit, and at six across they crowded out the ones you do. --}}
    <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-faint">{{ __('app.dashboard.at_a_glance') }}</h2>
    <div class="mb-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-ui.stat :label="__('app.dashboard.weight')" :value="$status['weight_kg'] ?? '—'" unit="kg"
                   :sub="isset($rate['kg_per_week']) ? sprintf('%+.2f kg/wk', $rate['kg_per_week']) : null" />
        <x-ui.stat :label="__('app.dashboard.body_fat')" :value="$status['fat_percent'] ?? '—'" unit="%"
                   :sub="$status['navy_fat_percent'] ? 'Navy '.$status['navy_fat_percent'].'%' : null"
                   :tip="__('app.tips.body_fat')" />
        <x-ui.stat :label="__('app.dashboard.lean_mass')" :value="$status['lean_mass_kg'] ?? '—'" unit="kg"
                   :sub="$status['ffmi_normalized'] ? 'FFMI '.$status['ffmi_normalized'] : null" />
        <x-ui.stat :label="__('app.body.fat_mass')" :value="$status['fat_mass_kg'] ?? '—'" unit="kg" />
    </div>

    <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-faint">{{ __('app.dashboard.trends') }}</h2>
    <div class="mb-8 grid gap-6 lg:grid-cols-2">
        <x-ui.card :title="__('app.body.weight_lean')">
            <x-multi-line-chart :sets="[
                ['series' => $weightSeries, 'label' => __('app.series.weight'), 'color' => \App\Support\Chart::series(0)],
                ['series' => $leanSeries, 'label' => __('app.series.lean_mass'), 'color' => \App\Support\Chart::series(2)],
            ]" :empty="__('app.chart.no_body_data')" />
        </x-ui.card>

        <x-ui.card :title="__('app.body.tape')" :subtitle="__('app.body.tape_sub')">
            <x-multi-line-chart :sets="[
                ['series' => $chestSeries, 'label' => __('app.series.chest'), 'color' => \App\Support\Chart::series(0)],
                ['series' => $waistSeries, 'label' => __('app.series.waist'), 'color' => \App\Support\Chart::series(3)],
                ['series' => $bicepSeries, 'label' => __('app.series.bicep'), 'color' => \App\Support\Chart::series(2)],
            ]" :empty="__('app.chart.no_tape_data')" />
        </x-ui.card>
    </div>

    {{-- Reference detail, collapsed. These answer "what is my FFMI" — a question
         you ask occasionally, not one you open the page for. Closed by default so
         the page ends where the useful part ends. --}}
    <details class="group rounded-xl border border-line bg-surface">
        <summary class="cursor-pointer list-none px-5 py-4 text-sm font-semibold text-ink marker:content-none">
            <span class="inline-flex items-center gap-2">
                <svg class="h-4 w-4 text-muted transition-transform group-open:rotate-90" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M7.5 5.5 12 10l-4.5 4.5V5.5z" />
                </svg>
                {{ __('app.body.detail') }}
            </span>
        </summary>

        <div class="space-y-6 border-t border-line px-5 py-5">
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <x-ui.stat :label="__('app.body.ffmi')" :value="$status['ffmi_normalized'] ?? '—'"
                           :sub="$status['ffmi'] ? __('app.body.ffmi_raw', ['value' => $status['ffmi']]) : null"
                           :tip="__('app.tips.ffmi')" />
                <x-ui.stat :label="__('app.body.waist_height')" :value="$status['waist_to_height'] ?? '—'"
                           :tone="($status['waist_to_height'] ?? 0) > 0.5 ? 'warn' : 'good'"
                           :tip="__('app.tips.waist_height')" />
                <x-ui.stat :label="__('app.body.p_ratio')" :value="$partitioning['p_ratio'] ?? '—'"
                           :sub="$partitioning['p_ratio'] !== null
                               ? ($partitioning['reliable'] ?? false
                                   ? __('app.body.sufficient_data', ['count' => $partitioning['fat_points'] ?? 0])
                                   : __('app.body.low_confidence', ['count' => $partitioning['fat_points'] ?? 0]))
                               : null"
                           :tip="__('app.tips.p_ratio')" />
                <x-ui.stat :label="__('app.body.source')"
                           :value="__('app.body.source_'.($partitioning['source'] ?? 'scale'))" />
            </div>

            @if (($partitioning['source'] ?? '') === 'scale')
                <p class="text-xs text-muted">
                    {!! __('app.body.bia_noisy', ['profile' => '<a href="'.route('profile.edit').'" class="underline">'.__('app.nav.profile').'</a>']) !!}
                </p>
            @endif

            <div class="grid gap-6 lg:grid-cols-2">
                <x-ui.card :title="__('app.body.ffmi_chart')" :subtitle="__('app.body.ffmi_chart_sub')">
                    <x-line-chart :series="$ffmiSeries" :label="__('app.series.ffmi')" :color="\App\Support\Chart::series(5)" />
                </x-ui.card>

                <x-ui.card :title="__('app.body.fat_chart')">
                    <x-line-chart :series="$fatSeries" :label="__('app.series.body_fat')" :color="\App\Support\Chart::series(4)" />
                </x-ui.card>
            </div>

            <x-ui.card :title="__('app.body.symmetry')" :subtitle="__('app.body.symmetry_sub')">
                @forelse ($symmetry as $s)
                    <div class="flex items-center justify-between border-b border-subtle py-1.5 text-sm last:border-0">
                        <span class="capitalize">{{ $s['part'] }}</span>
                        <span class="text-muted">L {{ $s['left'] }} · R {{ $s['right'] }}</span>
                        <span class="font-semibold {{ $s['diff_pct'] > 5 ? 'text-warn' : 'text-good' }}">{{ $s['diff_pct'] }}%</span>
                    </div>
                @empty
                    <p class="text-sm text-muted">{{ __('app.body.no_symmetry') }}</p>
                @endforelse
            </x-ui.card>
        </div>
    </details>
</x-ui.page>
