<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800">Nutrition</h2>
            <form method="POST" action="{{ route('nutrition.recompute') }}">
                @csrf
                <button class="text-xs rounded-md bg-gray-800 text-white px-3 py-1.5 hover:bg-gray-700">Recompute targets</button>
            </form>
        </div>
    </x-slot>

    <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <x-flash />

        @if(! $target)
            <x-panel>
                <p class="text-sm text-gray-600">Add your <a href="{{ route('profile.edit') }}" class="text-indigo-600 underline">height, age & sex</a>, set a <a href="{{ route('goals') }}" class="text-indigo-600 underline">goal</a>, and log a body weight in Hevy — then targets will appear here.</p>
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
                        <label class="text-xs text-gray-600 col-span-2">Date
                            <input type="date" name="date" value="{{ now()->toDateString() }}" class="mt-1 w-full rounded-md border-gray-300 text-sm" required>
                        </label>
                        <label class="text-xs text-gray-600">Calories
                            <input type="number" step="1" name="calories" class="mt-1 w-full rounded-md border-gray-300 text-sm" placeholder="{{ (int)$target->target_calories }}">
                        </label>
                        <label class="text-xs text-gray-600">Weight (kg)
                            <input type="number" step="0.1" name="weight_kg" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                        </label>
                        <label class="text-xs text-gray-600">Body fat (%, optional)
                            <input type="number" step="0.1" name="fat_percent" class="mt-1 w-full rounded-md border-gray-300 text-sm" placeholder="manual estimate">
                        </label>
                        <label class="text-xs text-gray-600">Protein (g)
                            <input type="number" step="1" name="protein_g" class="mt-1 w-full rounded-md border-gray-300 text-sm" placeholder="{{ (int)$target->protein_g }}">
                        </label>
                        <label class="text-xs text-gray-600">Fat (g)
                            <input type="number" step="1" name="fat_g" class="mt-1 w-full rounded-md border-gray-300 text-sm" placeholder="{{ (int)$target->fat_g }}">
                        </label>
                        <label class="text-xs text-gray-600">Carbs (g)
                            <input type="number" step="1" name="carb_g" class="mt-1 w-full rounded-md border-gray-300 text-sm" placeholder="{{ (int)$target->carb_g }}">
                        </label>
                        <div class="col-span-2">
                            <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Log intake</button>
                        </div>
                    </form>
                </x-panel>
            </div>

            <x-panel title="Recent intake vs target">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead><tr class="text-left text-xs uppercase text-gray-500 border-b">
                            <th class="py-2 pr-4">Date</th><th class="py-2 pr-4">Calories</th><th class="py-2 pr-4">Target</th>
                            <th class="py-2 pr-4">Protein</th><th class="py-2 pr-4">Weight</th>
                        </tr></thead>
                        <tbody>
                        @forelse($adherence as $row)
                            <tr class="border-b border-gray-50">
                                <td class="py-2 pr-4">{{ $row['date'] }}</td>
                                <td class="py-2 pr-4">{{ $row['calories'] ? number_format($row['calories']) : '—' }}</td>
                                <td class="py-2 pr-4 text-gray-500">{{ $row['target_calories'] ? number_format($row['target_calories']) : '—' }}</td>
                                <td class="py-2 pr-4">{{ $row['protein_g'] ? $row['protein_g'].'g' : '—' }}</td>
                                <td class="py-2 pr-4">{{ collect($recentLogs)->firstWhere('date', \Illuminate\Support\Carbon::parse($row['date']))?->weight_kg ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-3 text-gray-500">No intake logged yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </x-panel>
        @endif
    </div>
</x-app-layout>
