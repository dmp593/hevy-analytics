<div id="perf-results">
    @if(!empty($strengthLevel))
        <x-panel title="Strength level" class="mb-6">
            <x-slot name="actions">
                <x-info title="Strength level" text="Your strength in this exercise compared to people of the same age, bodyweight and sex. 0% = beginner, 100% = elite. Based on Strength Level community standards." />
            </x-slot>
            <x-strength-bar :eval="$strengthLevel" />
        </x-panel>
    @endif

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <x-stat label="Tonnage" :value="number_format($tonnage)" unit="kg" tone="accent" />
        <x-stat label="Working sets" :value="number_format($totalSets)" />
        <x-stat label="Total reps" :value="number_format($totalReps)" />
        @if($strengthScores)
            <x-stat label="Top e1RM Wilks" :value="$strengthScores['wilks'] ?? '—'" :sub="'DOTS '.($strengthScores['dots'] ?? '—')" />
        @else
            <x-stat label="Exercises" :value="count($prs)" />
        @endif
    </div>

    <div class="grid lg:grid-cols-2 gap-6 mb-6">
        <x-panel title="Volume trend" subtitle="Tonnage by {{ $filter->period }}">
            <x-line-chart :series="$tonnageSeries" label="Tonnage (kg)" color="#4f46e5"
                          empty="No data for these filters." />
        </x-panel>

        <x-panel title="Estimated 1RM" subtitle="{{ $filter->exerciseTemplateHevyId ? 'Best e1RM per session' : 'Select a single exercise to see e1RM progression' }}">
            <x-line-chart :series="$e1rmSeries" label="e1RM (kg)" color="#16a34a"
                          empty="Pick one exercise in the filter to chart its estimated 1RM (Epley/Brzycki) over time." />
        </x-panel>
    </div>

    <x-panel title="Exercise records" subtitle="Best e1RM, top weight, rep PR & best set volume">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase text-gray-500 border-b">
                        <th class="py-2 pr-4">Exercise</th>
                        <th class="py-2 pr-4">Muscle</th>
                        <th class="py-2 pr-4">Best e1RM</th>
                        <th class="py-2 pr-4">Top weight</th>
                        <th class="py-2 pr-4">Rep PR</th>
                        <th class="py-2 pr-4">Best set vol</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(array_slice($prs, 0, 25) as $p)
                        <tr class="border-b border-gray-50">
                            <td class="py-2 pr-4 font-medium">{{ $p['exercise'] }}</td>
                            <td class="py-2 pr-4 capitalize text-gray-500">{{ str_replace('_',' ', $p['muscle'] ?? '—') }}</td>
                            <td class="py-2 pr-4">{{ $p['best_e1rm'] ? round($p['best_e1rm'],1).' kg' : '—' }}</td>
                            <td class="py-2 pr-4">{{ $p['best_weight'] ? round($p['best_weight'],1).' kg' : '—' }}</td>
                            <td class="py-2 pr-4">{{ $p['best_reps'] ?: '—' }}</td>
                            <td class="py-2 pr-4">{{ $p['best_volume_set'] ? number_format($p['best_volume_set']).' kg' : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-3 text-gray-500">No exercises match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-panel>
</div>
