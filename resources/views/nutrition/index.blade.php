<x-ui.page :help="__('app.help.nutrition')" help-anchor="nutrition" :title="__('app.pages.nutrition')" :subtitle="__('app.pages.nutrition_sub')">
    <x-slot:actions>
        <form method="POST" action="{{ route('nutrition.recompute') }}">
            @csrf
            <x-ui.button type="submit" variant="secondary" size="sm">{{ __('app.nutrition.recompute') }}</x-ui.button>
        </form>
    </x-slot:actions>

    <x-flash />

    @if (! $target)
        <x-ui.card>
            <p class="text-sm text-body">
                {!! __('app.nutrition.setup', [
                    'profile' => '<a href="'.route('profile.edit').'" class="text-brand-ink underline">'.__('app.nutrition.setup_profile').'</a>',
                    'goal' => '<a href="'.route('goals').'" class="text-brand-ink underline">'.__('app.nutrition.setup_goal').'</a>',
                ]) !!}
            </p>
        </x-ui.card>
    @else
        @php $basis = $target->basis ?? []; @endphp

        {{-- What the app has actually learned about this athlete's metabolism.

             adaptiveMaintenance() derives real maintenance from logged intake
             against the observed weight trend — the one number here a calorie
             calculator cannot produce. It used to appear as the word "adaptive"
             and a figure in a 12px grey subtitle under a tile labelled with a
             formula's name. --}}
        @if ($verdict)
            <x-ui.insight :tone="$verdict['tone']" :title="$verdict['headline']" class="mb-6">
                <p>{{ $verdict['body'] }}</p>

                @if ($verdict['measured'] !== null && $verdict['formula'] !== null)
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-surface-sunk px-2.5 py-1 text-xs">
                            <span class="text-muted">{{ __('app.nutrition.verdict.measured') }}</span>
                            <span class="font-semibold text-ink">{{ number_format($verdict['measured']) }} kcal</span>
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-surface-sunk px-2.5 py-1 text-xs">
                            <span class="text-muted">{{ __('app.nutrition.verdict.formula') }}</span>
                            <span class="font-semibold text-ink">{{ number_format($verdict['formula']) }} kcal</span>
                        </span>
                    </div>
                @endif
            </x-ui.insight>
        @endif

        <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-faint">{{ __('app.nutrition.verdict.targets') }}</h2>
        <div class="mb-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-ui.stat :label="__('app.nutrition.target_calories')" :value="number_format($target->target_calories)" unit="kcal" tone="accent"
                       :sub="sprintf('%+.0f%%', $basis['adjustment_pct'] ?? 0).' · '.__('app.units.bw_per_week', ['value' => sprintf('%+.2f', $basis['target_rate_pct_bw_per_week'] ?? 0)])"
                       :tip="__('app.tips.target_calories')" tip-anchor="nutrition" />
            <x-ui.stat :label="__('app.nutrition.protein')" :value="$target->protein_g" unit="g" tone="good"
                       :sub="($basis['protein_g_per_kg'] ?? '').' g/kg'" :tip="__('app.tips.protein')" tip-anchor="nutrition" />
            <x-ui.stat :label="__('app.nutrition.fat')" :value="$target->fat_g" unit="g"
                       :sub="($basis['fat_g_per_kg'] ?? '').' g/kg'" :tip="__('app.tips.fat')" tip-anchor="nutrition" />
            <x-ui.stat :label="__('app.nutrition.carbs')" :value="$target->carb_g" unit="g"
                       :sub="__('app.nutrition.fiber', ['value' => $basis['fiber_g'] ?? '—'])" :tip="__('app.tips.carbs')" tip-anchor="nutrition" />
        </div>

        <div class="mb-8 grid gap-6 lg:grid-cols-2">
            <x-ui.card :title="__('app.nutrition.verdict.log')" :subtitle="__('app.nutrition.verdict.log_sub')">
                <form method="POST" action="{{ route('nutrition.intake') }}" class="grid grid-cols-2 gap-3">
                    @csrf
                    <label class="form-label col-span-2">{{ __('app.nutrition.date') }}
                        <input type="date" name="date" value="{{ now()->toDateString() }}" class="form-control" required>
                    </label>
                    <label class="form-label">{{ __('app.nutrition.calories') }}
                        <input type="number" step="1" name="calories" class="form-control" placeholder="{{ (int) $target->target_calories }}">
                    </label>
                    <label class="form-label">{{ __('app.nutrition.weight', ['unit' => units()->weightUnit()]) }}
                        <input type="number" step="0.1" name="weight" class="form-control">
                    </label>
                    <label class="form-label">{{ __('app.nutrition.protein') }} (g)
                        <input type="number" step="1" name="protein_g" class="form-control" placeholder="{{ (int) $target->protein_g }}">
                    </label>
                    <label class="form-label">{{ __('app.nutrition.fat') }} (g)
                        <input type="number" step="1" name="fat_g" class="form-control" placeholder="{{ (int) $target->fat_g }}">
                    </label>
                    <label class="form-label">{{ __('app.nutrition.carbs') }} (g)
                        <input type="number" step="1" name="carb_g" class="form-control" placeholder="{{ (int) $target->carb_g }}">
                    </label>
                    <label class="form-label">{{ __('app.nutrition.body_fat_optional') }}
                        <input type="number" step="0.1" name="fat_percent" class="form-control" placeholder="{{ __('app.nutrition.manual_estimate') }}">
                    </label>
                    <div class="col-span-2">
                        <x-ui.button type="submit">{{ __('app.nutrition.log_intake') }}</x-ui.button>
                    </div>
                </form>
            </x-ui.card>

            <x-ui.card :title="__('app.nutrition.macro_split')" :subtitle="__('app.nutrition.macro_split_sub')">
                <x-chart type="doughnut" :height="260"
                    :labels="[__('app.nutrition.protein'), __('app.nutrition.fat'), __('app.nutrition.carbs')]"
                    :datasets="[[
                        'data' => [$target->protein_g * 4, $target->fat_g * 9, $target->carb_g * 4],
                        'backgroundColor' => [\App\Support\Chart::series(2), \App\Support\Chart::series(3), \App\Support\Chart::series(0)],
                    ]]" />
            </x-ui.card>
        </div>

        <x-ui.card :title="__('app.nutrition.recent')" :subtitle="__('app.nutrition.recent_sub')" flush class="mb-6">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="table-head">
                            <th class="py-2 pl-5 pr-4">{{ __('app.nutrition.date') }}</th>
                            <th class="py-2 pr-4">{{ __('app.nutrition.calories') }}</th>
                            <th class="py-2 pr-4">{{ __('app.nutrition.target') }}</th>
                            <th class="py-2 pr-4">{{ __('app.nutrition.protein') }}</th>
                            <th class="py-2 pr-5">{{ __('app.nutrition.weight', ['unit' => units()->weightUnit()]) }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($adherence as $row)
                            <tr class="table-row">
                                <td class="py-2 pl-5 pr-4">{{ $row['date'] }}</td>
                                <td class="py-2 pr-4">{{ $row['calories'] ? number_format($row['calories']) : '—' }}</td>
                                <td class="py-2 pr-4 text-muted">{{ $row['target_calories'] ? number_format($row['target_calories']) : '—' }}</td>
                                <td class="py-2 pr-4">{{ $row['protein_g'] ? $row['protein_g'].'g' : '—' }}</td>
                                <td class="py-2 pr-5">{{ units()->weight($row['weight_kg']) ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-3 text-muted">{{ __('app.nutrition.no_intake') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.card>

        {{-- The derivation, for anyone who wants to check it. BMR and TDEE are
             inputs to the target, not numbers to act on, so they live here
             rather than competing with the macros for the first screen. --}}
        <details class="group rounded-xl border border-line bg-surface">
            <summary class="cursor-pointer list-none px-5 py-4 text-sm font-semibold text-ink marker:content-none">
                <span class="inline-flex items-center gap-2">
                    <svg class="h-4 w-4 text-muted transition-transform group-open:rotate-90" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M7.5 5.5 12 10l-4.5 4.5V5.5z" />
                    </svg>
                    {{ __('app.nutrition.verdict.how') }}
                </span>
            </summary>

            <div class="space-y-4 border-t border-line px-5 py-5">
                <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <x-ui.stat :label="__('app.nutrition.bmr', [
                                   'formula' => $target->bmr_formula === 'katch_mcardle' ? 'Katch-McArdle' : 'Mifflin-St Jeor',
                               ])"
                               :value="number_format($target->bmr)" unit="kcal" :tip="__('app.tips.bmr')" tip-anchor="nutrition" />
                    <x-ui.stat :label="__('app.nutrition.verdict.formula')" :value="$verdict['formula'] ? number_format($verdict['formula']) : '—'" unit="kcal"
                               :tip="__('app.tips.formula_tdee')" tip-anchor="nutrition" />
                    <x-ui.stat :label="__('app.nutrition.verdict.measured')" :value="$verdict['measured'] ? number_format($verdict['measured']) : '—'" unit="kcal"
                               :sub="$verdict['measured'] ? __('app.nutrition.from_days', ['days' => $loggedDays]) : null"
                               :tip="__('app.tips.adaptive')" tip-anchor="nutrition" />
                    <x-ui.stat :label="__('app.nutrition.used')" :value="number_format($target->tdee)" unit="kcal"
                               :tip="__('app.tips.blended')" tip-anchor="nutrition" />
                </div>

                @if ($verdict['measured'] !== null)
                    <p class="text-xs text-muted">{{ __('app.nutrition.verdict.blend_note') }}</p>
                @endif
            </div>
        </details>
    @endif

    {{-- Outside the target gate on purpose: someone arriving with months of
         MyFitnessPal history should be able to bring it before anything else
         is configured — the history is what makes the measurements real. --}}
    <x-ui.card :title="__('app.nutrition.import.title')" class="mt-6">
        <p class="text-sm text-body">{{ __('app.nutrition.import.help') }}</p>
        <p class="mt-1 text-xs text-muted">{{ __('app.nutrition.import.sources_hint') }}</p>

        <form method="POST" action="{{ route('nutrition.import') }}" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-center gap-3">
            @csrf
            <input type="file" name="file" accept=".csv,text/csv" required
                   class="form-file">
            <x-ui.button type="submit">{{ __('app.nutrition.import.submit') }}</x-ui.button>
        </form>
        <x-input-error class="mt-2" :messages="$errors->get('file')" />

        <p class="mt-3 text-xs text-faint">{{ __('app.nutrition.import.idempotent') }}</p>
    </x-ui.card>

    {{-- Steps & sleep: context for the numbers above, never prescriptions.
         Steps sanity-check the stated activity level the formula TDEE rests
         on; sleep is shown because recovery is where growth happens. --}}
    <x-ui.card :title="__('app.health.title')" :subtitle="__('app.health.sub')" class="mt-6">
        @if ($health['avg_steps'] !== null || $health['avg_sleep_minutes'] !== null)
            <div class="mb-4 grid grid-cols-2 gap-4">
                <x-ui.stat :label="__('app.health.avg_steps')"
                           :value="$health['avg_steps'] !== null ? number_format($health['avg_steps']) : '—'" />
                <x-ui.stat :label="__('app.health.avg_sleep')"
                           :value="$health['avg_sleep_minutes'] !== null ? sprintf('%dh%02d', intdiv($health['avg_sleep_minutes'], 60), $health['avg_sleep_minutes'] % 60) : '—'" />
            </div>

            @if ($health['activity_hint'])
                <x-ui.insight tone="info" :title="__('app.health.activity_mismatch')" class="mb-4">
                    {{ __('app.health.'.$health['activity_hint']) }}
                </x-ui.insight>
            @endif
        @endif

        <p class="text-sm text-body">{{ __('app.health.help') }}</p>
        <p class="mt-1 text-xs text-muted">{{ __('app.health.sources_hint') }}</p>

        <form method="POST" action="{{ route('nutrition.health') }}" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-center gap-3">
            @csrf
            <input type="file" name="file" accept=".csv,text/csv" required
                   class="form-file">
            <x-ui.button type="submit">{{ __('app.nutrition.import.submit') }}</x-ui.button>
        </form>

        <p class="mt-3 text-xs text-faint">{{ __('app.nutrition.import.idempotent') }}</p>
    </x-ui.card>
</x-ui.page>
