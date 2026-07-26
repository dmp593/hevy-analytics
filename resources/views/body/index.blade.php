<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800">Body composition</h2></x-slot>

    <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <x-flash />

        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-6">
            <x-stat label="Weight" :value="$status['weight_kg'] ?? '—'" unit="kg" />
            <x-stat label="Body fat" :value="$status['fat_percent'] ?? '—'" unit="%" :sub="$status['navy_fat_percent'] ? 'Navy '.$status['navy_fat_percent'].'%' : null" />
            <x-stat label="Lean mass" :value="$status['lean_mass_kg'] ?? '—'" unit="kg" />
            <x-stat label="Fat mass" :value="$status['fat_mass_kg'] ?? '—'" unit="kg" />
            <x-stat label="FFMI (norm.)" :value="$status['ffmi_normalized'] ?? '—'" :sub="$status['ffmi'] ? 'raw '.$status['ffmi'] : null" tone="accent"
                    tip="Muscularity adjusted for height, standardised to 1.80m. ~19 average, ~22 fit, ~25 near natural ceiling." />
            <x-stat label="Waist/height" :value="$status['waist_to_height'] ?? '—'"
                    :tone="($status['waist_to_height'] ?? 0) > 0.5 ? 'warn' : 'good'"
                    tip="Waist ÷ height. Keep under 0.5. Rising during a bulk means fat gain around the middle." />
        </div>

        <div class="grid lg:grid-cols-2 gap-6 mb-6">
            <x-panel title="Weight & lean mass">
                <x-chart
                    :labels="\App\Support\Chart::labels($weightSeries)"
                    :datasets="[
                        \App\Support\Chart::line($weightSeries, 'Weight (kg)', '#0ea5e9', false),
                        \App\Support\Chart::line($leanSeries, 'Lean mass (kg)', '#16a34a', false),
                    ]" />
            </x-panel>
            <x-panel title="Body fat %">
                <x-line-chart :series="$fatSeries" label="Body fat %" color="#ef4444" />
            </x-panel>
            <x-panel title="Normalized FFMI" subtitle="Natural ceiling ≈ 25">
                <x-line-chart :series="$ffmiSeries" label="FFMI" color="#8b5cf6" />
            </x-panel>
            <x-panel title="Circumferences (cm)">
                <x-chart
                    :labels="\App\Support\Chart::labels($chestSeries)"
                    :datasets="[
                        \App\Support\Chart::line($chestSeries, 'Chest', '#4f46e5', false),
                        \App\Support\Chart::line($waistSeries, 'Waist', '#f59e0b', false),
                        \App\Support\Chart::line($bicepSeries, 'Bicep (R)', '#10b981', false),
                    ]" />
            </x-panel>
        </div>

        <div class="grid lg:grid-cols-2 gap-6">
            <x-panel title="Left / right symmetry">
                @forelse($symmetry as $s)
                    <div class="flex items-center justify-between py-1.5 border-b border-gray-50 text-sm">
                        <span class="capitalize">{{ $s['part'] }}</span>
                        <span class="text-gray-500">L {{ $s['left'] }} · R {{ $s['right'] }}</span>
                        <span class="{{ $s['diff_pct'] > 5 ? 'text-amber-600' : 'text-green-600' }} font-semibold">{{ $s['diff_pct'] }}%</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No paired circumference data.</p>
                @endforelse
            </x-panel>

            <x-panel title="Partitioning & rate">
                <x-slot name="actions">
                    <x-info title="P-ratio" text="Of the weight you've gained, the fraction that was lean mass (muscle) vs fat, from a trend over many readings. BIA scales are noisy — treat as a rough guide and cross-check the mirror, waist and strength." />
                </x-slot>
                <dl class="text-sm space-y-2">
                    <div class="flex justify-between"><dt class="text-gray-500">Weight rate</dt><dd>{{ $rate['kg_per_week'] ?? '—' }} kg/wk ({{ $rate['pct_bw_per_week'] ?? '—' }}%BW)</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Δ Lean (90d trend)</dt><dd>{{ $partitioning['delta_lean_kg'] ?? '—' }} kg</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Δ Weight (90d trend)</dt><dd>{{ $partitioning['delta_weight_kg'] ?? '—' }} kg</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Δ Body fat (90d trend)</dt><dd>{{ $partitioning['delta_fat_pct'] ?? '—' }} pts</dd></div>
                    <div class="flex justify-between font-semibold"><dt>P-ratio (lean/total)</dt><dd>{{ $partitioning['p_ratio'] ?? '—' }}</dd></div>
                </dl>
                <div class="mt-3 flex items-center gap-2 text-xs">
                    <span class="rounded-full bg-gray-100 px-2 py-0.5">Source: {{ ucfirst($partitioning['source'] ?? 'scale') }}{{ ($partitioning['source'] ?? '') === 'scale' ? ' (BIA)' : '' }}</span>
                    @if($partitioning['p_ratio'] !== null)
                        <span class="rounded-full px-2 py-0.5 {{ ($partitioning['reliable'] ?? false) ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                            {{ ($partitioning['reliable'] ?? false) ? 'sufficient data' : 'low confidence' }} · {{ $partitioning['fat_points'] ?? 0 }} readings
                        </span>
                    @endif
                </div>
                @if(($partitioning['source'] ?? '') === 'scale')
                    <p class="mt-2 text-xs text-gray-400">BIA scale body-fat is noisy. Switch source in <a href="{{ route('profile.edit') }}" class="underline">Profile</a> (Navy tape / manual) for steadier tracking.</p>
                @endif

                <div class="mt-4 border-t border-gray-100 pt-3">
                    <div class="text-xs font-semibold text-gray-500 mb-2">Corroborating signals (trust these together)</div>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        @php
                            $sig = function($label, $val, $unit, $goodUp) {
                                if ($val === null) return ['label'=>$label,'text'=>'—','tone'=>'text-gray-400'];
                                $arrow = $val > 0 ? '↑' : ($val < 0 ? '↓' : '→');
                                $good = $goodUp ? $val > 0 : $val < 0;
                                return ['label'=>$label,'text'=>$arrow.' '.abs($val).$unit,'tone'=>$good?'text-green-600':'text-amber-600'];
                            };
                            $signals = [
                                $sig('Weight', $triangulation['weight']['kg_per_week'] ?? null, ' kg/wk', true),
                                $sig('Chest', $triangulation['chest']['per_month'] ?? null, ' cm/mo', true),
                                $sig('Bicep', $triangulation['bicep']['per_month'] ?? null, ' cm/mo', true),
                                $sig('Waist', $triangulation['waist']['per_month'] ?? null, ' cm/mo', false),
                            ];
                        @endphp
                        @foreach($signals as $s)
                            <div class="flex justify-between rounded bg-gray-50 px-2 py-1">
                                <span class="text-gray-500">{{ $s['label'] }}</span>
                                <span class="font-semibold {{ $s['tone'] }}">{{ $s['text'] }}</span>
                            </div>
                        @endforeach
                        @if($triangulation['lift'])
                            <div class="col-span-2 flex justify-between rounded bg-gray-50 px-2 py-1">
                                <span class="text-gray-500">Top lift ({{ $triangulation['lift']['exercise'] }})</span>
                                <span class="font-semibold {{ $triangulation['lift']['trend'] === 'up' ? 'text-green-600' : 'text-amber-600' }}">
                                    strength {{ $triangulation['lift']['trend'] }}
                                </span>
                            </div>
                        @endif
                    </div>
                    <p class="mt-2 text-xs text-gray-400">Muscle gain: chest/arms &amp; strength rising while waist stays flat. That's more trustworthy than any single body-fat reading.</p>
                </div>
            </x-panel>
        </div>
    </div>
</x-app-layout>
