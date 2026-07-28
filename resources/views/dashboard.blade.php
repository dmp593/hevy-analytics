@php
    $meta = ($needsSetup ?? false) ? null
        : __('app.dashboard.workouts', ['count' => number_format($workoutCount)])
            .' · '.__('app.sync.last_synced', [
                'when' => $lastSync ? $lastSync->diffForHumans() : __('app.sync.never'),
            ]);
@endphp

<x-ui.page :title="__('app.dashboard.title')" :meta="$meta">
    <x-flash />
    <x-sync-banner :status="$syncStatus ?? null" />

    @if ($needsSetup ?? false)
        {{-- First run. A new account previously landed on a wall of em-dashes and
             blank charts, which looks broken rather than empty. --}}
        <x-ui.card :title="__('app.dashboard.welcome_title')" class="mb-6">
            <p class="max-w-prose text-sm text-body">{{ __('app.dashboard.welcome_body') }}</p>
            <div class="mt-4 flex flex-wrap gap-3">
                <x-ui.button :href="route('profile.edit')">{{ __('app.dashboard.set_up_profile') }}</x-ui.button>
                {{-- The door for Hevy accounts without Pro: same data, one file. --}}
                <x-ui.button :href="route('import')" variant="secondary">{{ __('app.import.title') }}</x-ui.button>
            </div>

            {{-- Units, decided on day one rather than discovered later in the
                 profile: someone about to type their first bodyweight needs the
                 field to already speak lb if that is how they think. --}}
            <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-subtle pt-4">
                <span class="text-xs font-medium text-muted">{{ __('app.profile.units') }}</span>
                @foreach ([\App\Support\Units::METRIC => 'kg · cm', \App\Support\Units::IMPERIAL => 'lb · ft/in'] as $system => $symbols)
                    <form method="POST" action="{{ route('settings.units', $system) }}">
                        @csrf
                        <button class="min-h-11 rounded-md border px-3 text-xs font-semibold transition {{ auth()->user()->unit_system === $system ? 'border-brand bg-brand-soft text-brand-ink' : 'border-line text-body hover:bg-surface-sunk' }}"
                                @if (auth()->user()->unit_system === $system) aria-pressed="true" @endif>
                            {{ __('app.profile.unit_'.$system.'_short') }} · {{ $symbols }}
                        </button>
                    </form>
                @endforeach
            </div>
        </x-ui.card>
        <x-onboarding :onboarding="$onboarding" />
    @else
        {{-- Key set, but the account is not fully wired yet: keep the checklist
             in sight until every step is done, then it disappears for good. --}}
        @unless (($onboarding ?? null)?->complete() ?? true)
            <div class="mb-6"><x-onboarding :onboarding="$onboarding" /></div>
        @endunless

        {{-- Young data reads as a broken product: weekly rates computed from
             four days swing wildly, and nothing used to say why. Said once,
             here, account-wide — and it disappears the day it stops being
             true. Individual pages keep their own low-confidence hedges. --}}
        @if (isset($confidence) && $confidence->sessions > 0 && ! $confidence->established())
            <x-ui.insight tone="info" :title="__('app.confidence.title')" class="mb-6">
                <p>{{ trans_choice('app.confidence.body', $confidence->sessions, [
                    'sessions' => $confidence->sessions,
                    'days' => $confidence->spanDays,
                    'needed_sessions' => \App\Support\DataConfidence::MIN_SESSIONS,
                    'needed_days' => \App\Support\DataConfidence::MIN_SPAN_DAYS,
                ]) }}</p>
            </x-ui.insight>
        @endif
        @php
            $goalLabel = $goal ? \App\Science\Goals\GoalType::label($goal->type) : __('app.dashboard.no_goal');
            $ratePct = $rate['pct_bw_per_week'] ?? null;

            // One alert is guidance; six is wallpaper. Lead with the most
            // actionable and fold the rest away.
            $leadAlerts = array_slice($alerts, 0, 2);
            $restAlerts = array_slice($alerts, 2);

            $toneFor = fn (string $level) => match ($level) {
                'success' => 'good',
                'warning' => 'warn',
                'danger' => 'bad',
                default => 'info',
            };
        @endphp

        <div class="space-y-8">
            {{-- 1. What should I do? Advice first, numbers after. --}}
            @if (count($leadAlerts))
                <section class="space-y-3" aria-label="{{ __('app.dashboard.whats_next') }}">
                    @foreach ($leadAlerts as $a)
                        <x-ui.insight :tone="$toneFor($a['level'])" :title="$a['title']">{{ $a['message'] }}</x-ui.insight>
                    @endforeach

                    @if (count($restAlerts))
                        <details class="group">
                            <summary class="cursor-pointer list-none text-xs font-medium text-muted transition hover:text-ink">
                                {{ __('app.dashboard.more_alerts', ['count' => count($restAlerts)]) }}
                                <span class="inline-block transition group-open:rotate-90" aria-hidden="true">›</span>
                            </summary>
                            <div class="mt-3 space-y-3">
                                @foreach ($restAlerts as $a)
                                    <x-ui.insight :tone="$toneFor($a['level'])" :title="$a['title']">{{ $a['message'] }}</x-ui.insight>
                                @endforeach
                            </div>
                        </details>
                    @endif
                </section>
            @endif

            {{-- 2. Where am I? Four tiles, not six: the two dropped (goal and
                 tonnage) are a label rather than a measurement, and a number that
                 rewards high-rep light work. --}}
            <section aria-label="{{ __('app.dashboard.at_a_glance') }}">
                <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-faint">{{ __('app.dashboard.at_a_glance') }}</h2>
                <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <x-ui.stat :label="__('app.dashboard.weight')" :value="units()->weight($status['weight_kg'] ?? null)" :unit="units()->weightUnit()"
                               :sub="$ratePct !== null ? __('app.units.bw_per_week', ['value' => sprintf('%+.2f', $ratePct)]) : null"
                               :tip="__('app.tips.weight')" />
                    <x-ui.stat :label="__('app.dashboard.body_fat')" :value="$status['fat_percent'] ?? null" unit="%"
                               :sub="$status['navy_fat_percent'] ? 'Navy '.$status['navy_fat_percent'].'%' : null"
                               :tip="__('app.tips.body_fat')" />
                    <x-ui.stat :label="__('app.dashboard.lean_mass')" :value="units()->weight($status['lean_mass_kg'] ?? null)" :unit="units()->weightUnit()"
                               :sub="$status['ffmi_normalized'] ? 'FFMI '.$status['ffmi_normalized'] : null"
                               :tip="__('app.tips.lean_mass')" />
                    <x-ui.stat :label="__('app.dashboard.hard_sets_4wk')" :value="$weekSets" tone="accent"
                               :sub="$goalLabel" :tip="__('app.tips.hard_sets')" />
                </div>
            </section>

            {{-- 3. Am I training enough? The single most valuable view in the
                 app, so it gets the width. --}}
            <section aria-label="{{ __('app.dashboard.weekly_sets') }}">
                <x-ui.card :title="__('app.dashboard.weekly_sets')" :subtitle="__('app.dashboard.weekly_sets_sub')">
                    <x-slot name="actions">
                        <x-info :title="__('app.dashboard.weekly_sets')" :text="__('app.tips.landmarks')" />
                    </x-slot>

                    @if (count($weeklySetsPerMuscle))
                        <x-muscle-volume-bars :items="$weeklySetsPerMuscle" :limit="10" :show-landmarks="false" />
                    @else
                        <x-ui.empty :title="__('app.dashboard.no_training_yet')">
                            {{ __('app.dashboard.no_training_yet_body') }}
                        </x-ui.empty>
                    @endif
                </x-ui.card>
            </section>

            {{-- 4. Trends. Two charts, side by side, given room to breathe. --}}
            <section class="grid gap-6 lg:grid-cols-2" aria-label="{{ __('app.dashboard.trends') }}">
                <x-ui.card :title="__('app.dashboard.training_volume')" :subtitle="__('app.dashboard.training_volume_sub')">
                    <x-line-chart :series="units()->weightSeries($tonnageSeries)" :label="__('app.series.tonnage', ['unit' => units()->weightUnit()])" :color="\App\Support\Chart::series(0)" />
                </x-ui.card>

                <x-ui.card :title="__('app.dashboard.body_composition')" :subtitle="__('app.dashboard.body_composition_sub')">
                    <x-multi-line-chart :sets="[
                        ['series' => units()->weightSeries($weightSeries), 'label' => __('app.series.weight', ['unit' => units()->weightUnit()]), 'color' => \App\Support\Chart::series(1)],
                        ['series' => units()->weightSeries($leanSeries), 'label' => __('app.series.lean_mass', ['unit' => units()->weightUnit()]), 'color' => \App\Support\Chart::series(2)],
                    ]" :empty="__('app.chart.no_body_data')" />
                </x-ui.card>
            </section>

            {{-- 5. Supporting detail, deliberately last. --}}
            <section class="grid gap-6 lg:grid-cols-2" aria-label="{{ __('app.dashboard.detail') }}">
                <x-ui.card :title="__('app.dashboard.muscle_balance')" :subtitle="__('app.dashboard.muscle_balance_sub')">
                    <x-balance-ratios :items="$balance" />
                </x-ui.card>

                @if ($nutrition)
                    <x-ui.card :title="__('app.dashboard.nutrition_targets')" :subtitle="__('app.dashboard.nutrition_targets_sub')">
                        <dl class="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-3">
                            @foreach ([
                                ['app.nutrition.maintenance', number_format($nutrition->tdee), 'kcal'],
                                ['app.nutrition.target_calories', number_format($nutrition->target_calories), 'kcal'],
                                ['app.nutrition.protein', $nutrition->protein_g, 'g'],
                                ['app.nutrition.fat', $nutrition->fat_g, 'g'],
                                ['app.nutrition.carbs', $nutrition->carb_g, 'g'],
                            ] as [$key, $value, $unit])
                                <div>
                                    <dt class="text-xs text-muted">{{ __($key) }}</dt>
                                    <dd class="text-lg font-semibold tabular-nums text-ink">
                                        {{ $value }}<span class="ml-0.5 text-xs font-normal text-muted">{{ $unit }}</span>
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                    </x-ui.card>
                @endif
            </section>
        </div>
    @endif
</x-ui.page>
