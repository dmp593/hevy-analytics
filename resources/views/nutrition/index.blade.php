<x-ui.page :title="__('app.pages.nutrition')" :subtitle="__('app.pages.nutrition_sub')">
    <x-slot:actions>
        <form method="POST" action="{{ route('nutrition.recompute') }}">
            @csrf
            <x-ui.button type="submit" variant="secondary" size="sm">{{ __('app.nutrition.recompute') }}</x-ui.button>
        </form>
    </x-slot:actions>

    <x-flash />

    @if(! $target)
        <x-panel>
            <p class="text-sm text-body">Add your <a href="{{ route('profile.edit') }}" class="text-brand-ink underline">height, age & sex</a>, set a <a href="{{ route('goals') }}" class="text-brand-ink underline">goal</a>, and log a body weight in Hevy — then targets will appear here.</p>
        </x-panel>
    @else
        @php $basis = $target->basis ?? []; @endphp
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
            <x-stat label="BMR ({{ $target->bmr_formula === 'katch_mcardle' ? 'Katch-McArdle' : 'Mifflin' }})" :value="number_format($target->bmr)" unit="kcal" />
            <x-stat label="Maintenance (TDEE)" :value="number_format($target->tdee)" unit="kcal" :sub="$adaptive ? 'adaptive '.number_format($adaptive) : null" />
            <x-stat label="Target calories" :value="number_format($target->target_calories)" unit="kcal" tone="accent"
                    :sub="sprintf('%+.0f%%', $basis['adjustment_pct'] ?? 0).' · '.__('app.units.bw_per_week', ['value' => sprintf('%+.2f', $basis['target_rate_pct_bw_per_week'] ?? 0)])" />
            <x-stat label="Protein" :value="$target->protein_g" unit="g" tone="good" :sub="($basis['protein_g_per_kg'] ?? '').' g/kg'" />
            <x-stat label="Fat" :value="$target->fat_g" unit="g" :sub="($basis['fat_g_per_kg'] ?? '').' g/kg'" />
            <x-stat label="Carbs" :value="$target->carb_g" unit="g" :sub="'fiber ~'.($basis['fiber_g'] ?? '—').'g'" />
        </div>

        <div class="grid lg:grid-cols-2 gap-6 mb-6">
            <x-panel title="Macro split" subtitle="Share of daily calories">
                <x-chart type="doughnut" :height="260"
                    :labels="['Protein','Fat','Carbs']"
                    :datasets="[[
                        'data' => [$target->protein_g*4, $target->fat_g*9, $target->carb_g*4],
                        'backgroundColor' => ['#16a34a','#f59e0b','#4f46e5'],
                    ]]" />
            </x-panel>

            <x-panel title="Log today's intake" subtitle="Feeds adaptive TDEE & adherence">
                <form method="POST" action="{{ route('nutrition.intake') }}" class="grid grid-cols-2 gap-3">
                    @csrf
                    <label class="text-xs text-body col-span-2">Date
                        <input type="date" name="date" value="{{ now()->toDateString() }}" class="mt-1 w-full rounded-md border-line text-sm" required>
                    </label>
                    <label class="text-xs text-body">Calories
                        <input type="number" step="1" name="calories" class="mt-1 w-full rounded-md border-line text-sm" placeholder="{{ (int)$target->target_calories }}">
                    </label>
                    <label class="text-xs text-body">Weight (kg)
                        <input type="number" step="0.1" name="weight_kg" class="mt-1 w-full rounded-md border-line text-sm">
                    </label>
                    <label class="text-xs text-body">Body fat (%, optional)
                        <input type="number" step="0.1" name="fat_percent" class="mt-1 w-full rounded-md border-line text-sm" placeholder="manual estimate">
                    </label>
                    <label class="text-xs text-body">Protein (g)
                        <input type="number" step="1" name="protein_g" class="mt-1 w-full rounded-md border-line text-sm" placeholder="{{ (int)$target->protein_g }}">
                    </label>
                    <label class="text-xs text-body">Fat (g)
                        <input type="number" step="1" name="fat_g" class="mt-1 w-full rounded-md border-line text-sm" placeholder="{{ (int)$target->fat_g }}">
                    </label>
                    <label class="text-xs text-body">Carbs (g)
                        <input type="number" step="1" name="carb_g" class="mt-1 w-full rounded-md border-line text-sm" placeholder="{{ (int)$target->carb_g }}">
                    </label>
                    <div class="col-span-2">
                        <button class="rounded-md bg-brand px-4 py-2 text-sm font-semibold text-on-fill hover:bg-brand-hover">Log intake</button>
                    </div>
                </form>
            </x-panel>
        </div>

        <x-panel title="Recent intake vs target">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead><tr class="text-left text-xs uppercase text-muted border-b">
                        <th class="py-2 pr-4">Date</th><th class="py-2 pr-4">Calories</th><th class="py-2 pr-4">Target</th>
                        <th class="py-2 pr-4">Protein</th><th class="py-2 pr-4">Weight</th>
                    </tr></thead>
                    <tbody>
                    @forelse($adherence as $row)
                        <tr class="border-b border-subtle">
                            <td class="py-2 pr-4">{{ $row['date'] }}</td>
                            <td class="py-2 pr-4">{{ $row['calories'] ? number_format($row['calories']) : '—' }}</td>
                            <td class="py-2 pr-4 text-muted">{{ $row['target_calories'] ? number_format($row['target_calories']) : '—' }}</td>
                            <td class="py-2 pr-4">{{ $row['protein_g'] ? $row['protein_g'].'g' : '—' }}</td>
                            <td class="py-2 pr-4">{{ collect($recentLogs)->firstWhere('date', \Illuminate\Support\Carbon::parse($row['date']))?->weight_kg ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-3 text-muted">No intake logged yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-panel>
    @endif
</x-ui.page>
