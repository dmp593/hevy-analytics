<x-ui.page :help="__('app.help.goals')" help-anchor="leanbulk" :title="__('app.pages.goals')" :subtitle="__('app.pages.goals_sub')" width="5xl">
    {{-- x-data lives on a plain element, not on <x-ui.page>: the expression
         contains @js(), and routing that through a component attribute
         re-escapes the JSON into something Alpine cannot parse. --}}
    <div x-data="{ type: '{{ $active->type ?? 'lean_bulk' }}', presets: @js($presets) }">
        <x-flash />

        <form method="POST" action="{{ route('goals.store') }}">
            @csrf
            <x-panel :title="__('app.goals.choose')" :subtitle="__('app.goals.choose_sub')" class="mb-6">
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($types as $key => $t)
                        <label class="cursor-pointer rounded-lg border p-4 transition"
                               :class="type === '{{ $key }}' ? 'border-brand ring-2 ring-brand/30 bg-brand-soft' : 'border-line'">
                            <input type="radio" name="type" value="{{ $key }}" class="sr-only" x-model="type">
                            <div class="font-semibold text-sm">{{ $t['label'] }}</div>
                            <div class="text-xs text-muted mt-1">{{ $t['description'] }}</div>
                        </label>
                    @endforeach
                </div>
            </x-panel>

            <x-panel :title="__('app.goals.fine_tune')" :subtitle="__('app.goals.fine_tune_sub')" class="mb-6">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <label class="text-xs text-body">{{ __('app.goals.calorie_adjustment') }}
                        <input type="number" step="0.5" name="calorie_adjustment_pct" class="mt-1 w-full rounded-md border-line text-sm"
                               value="{{ old('calorie_adjustment_pct', $active?->calorie_adjustment_pct) }}"
                               :placeholder="presets[type]?.calorie_adjustment_pct">
                    </label>
                    <label class="text-xs text-body">{{ __('app.goals.protein_per_kg') }}
                        <input type="number" step="0.1" name="protein_g_per_kg" class="mt-1 w-full rounded-md border-line text-sm"
                               value="{{ old('protein_g_per_kg', $active?->protein_g_per_kg) }}"
                               :placeholder="presets[type]?.protein_g_per_kg">
                    </label>
                    <label class="text-xs text-body">{{ __('app.goals.fat_per_kg') }}
                        <input type="number" step="0.1" name="fat_g_per_kg" class="mt-1 w-full rounded-md border-line text-sm"
                               value="{{ old('fat_g_per_kg', $active?->fat_g_per_kg) }}"
                               :placeholder="presets[type]?.fat_g_per_kg">
                    </label>
                    <label class="text-xs text-body">{{ __('app.goals.rate') }}
                        <input type="number" step="0.05" name="target_rate_pct_bw_per_week" class="mt-1 w-full rounded-md border-line text-sm"
                               value="{{ old('target_rate_pct_bw_per_week', $active?->target_rate_pct_bw_per_week) }}"
                               :placeholder="presets[type]?.target_rate_pct_bw_per_week">
                    </label>
                </div>
                <div class="mt-4 rounded-lg bg-surface-sunk p-3 text-xs text-body">
                    <span class="font-semibold">{{ __('app.goals.training_profile') }}</span>
                    <span x-text="presets[type]?.training?.name"></span> ·
                    {{ __('app.goals.reps') }} <span x-text="presets[type]?.training?.rep_range"></span> ·
                    <span x-text="presets[type]?.training?.sets_min"></span>–<span x-text="presets[type]?.training?.sets_max"></span> {{ __('app.goals.sets_per_muscle') }} ·
                    RIR <span x-text="presets[type]?.training?.rir_min"></span>–<span x-text="presets[type]?.training?.rir_max"></span>
                </div>
            </x-panel>

            <button class="rounded-md bg-brand px-5 py-2.5 text-sm font-semibold text-on-fill hover:bg-brand-hover">{{ __('app.goals.save') }}</button>
        </form>

        @if($history->count())
            <x-panel :title="__('app.goals.history')" class="mt-6">
                <div class="space-y-1 text-sm">
                    @foreach($history as $g)
                        <div class="flex justify-between py-1 border-b border-subtle">
                            <span>{{ \App\Science\Goals\GoalType::label($g->type) }} @if($g->is_active)<span class="text-xs text-good">{{ __('app.goals.active') }}</span>@endif</span>
                            <span class="text-muted">{{ $g->started_at?->toDateString() }}</span>
                        </div>
                    @endforeach
                </div>
            </x-panel>
        @endif
    </div>
</x-ui.page>
