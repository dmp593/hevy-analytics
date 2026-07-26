<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800">Goals</h2></x-slot>

    <div class="py-8 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8" x-data="{ type: '{{ $active->type ?? 'lean_bulk' }}', presets: @js($presets) }">
        <x-flash />

        <form method="POST" action="{{ route('goals.store') }}">
            @csrf
            <x-panel title="Choose a goal" subtitle="Adjusts your diet targets and training landmarks" class="mb-6">
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($types as $key => $t)
                        <label class="cursor-pointer rounded-lg border p-4 transition"
                               :class="type === '{{ $key }}' ? 'border-indigo-500 ring-2 ring-indigo-200 bg-indigo-50' : 'border-gray-200'">
                            <input type="radio" name="type" value="{{ $key }}" class="sr-only" x-model="type">
                            <div class="font-semibold text-sm">{{ $t['label'] }}</div>
                            <div class="text-xs text-gray-500 mt-1">{{ $t['description'] }}</div>
                        </label>
                    @endforeach
                </div>
            </x-panel>

            <x-panel title="Fine-tune (optional)" subtitle="Leave blank to use the evidence-based preset default" class="mb-6">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <label class="text-xs text-gray-600">Calorie adj. %
                        <input type="number" step="0.5" name="calorie_adjustment_pct" class="mt-1 w-full rounded-md border-gray-300 text-sm"
                               :placeholder="presets[type]?.calorie_adjustment_pct">
                    </label>
                    <label class="text-xs text-gray-600">Protein g/kg
                        <input type="number" step="0.1" name="protein_g_per_kg" class="mt-1 w-full rounded-md border-gray-300 text-sm"
                               :placeholder="presets[type]?.protein_g_per_kg">
                    </label>
                    <label class="text-xs text-gray-600">Fat g/kg
                        <input type="number" step="0.1" name="fat_g_per_kg" class="mt-1 w-full rounded-md border-gray-300 text-sm"
                               :placeholder="presets[type]?.fat_g_per_kg">
                    </label>
                    <label class="text-xs text-gray-600">Rate %BW/wk
                        <input type="number" step="0.05" name="target_rate_pct_bw_per_week" class="mt-1 w-full rounded-md border-gray-300 text-sm"
                               :placeholder="presets[type]?.target_rate_pct_bw_per_week">
                    </label>
                </div>
                <div class="mt-4 rounded-lg bg-gray-50 p-3 text-xs text-gray-600">
                    <span class="font-semibold">Training profile:</span>
                    <span x-text="presets[type]?.training?.name"></span> ·
                    reps <span x-text="presets[type]?.training?.rep_range"></span> ·
                    <span x-text="presets[type]?.training?.sets_min"></span>–<span x-text="presets[type]?.training?.sets_max"></span> sets/muscle/wk ·
                    RIR <span x-text="presets[type]?.training?.rir_min"></span>–<span x-text="presets[type]?.training?.rir_max"></span>
                </div>
            </x-panel>

            <button class="rounded-md bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Save goal</button>
        </form>

        @if($history->count())
            <x-panel title="History" class="mt-6">
                <div class="space-y-1 text-sm">
                    @foreach($history as $g)
                        <div class="flex justify-between py-1 border-b border-gray-50">
                            <span>{{ \App\Science\Goals\GoalType::label($g->type) }} @if($g->is_active)<span class="text-xs text-green-600">(active)</span>@endif</span>
                            <span class="text-gray-500">{{ $g->started_at?->toDateString() }}</span>
                        </div>
                    @endforeach
                </div>
            </x-panel>
        @endif
    </div>
</x-app-layout>
