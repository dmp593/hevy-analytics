{{-- The variable is NOT escaped: `\$routine` compiles to a literal backslash in
     the attribute expression, which is a PHP parse error, and the page 500s. --}}
<x-ui.page :title="__('app.pages.routine_edit', ['name' => $routine->title])" width="4xl">
    <x-flash />

    {{-- Titled "assisted", not "AI-assisted": no model is involved. It is double
         progression off the athlete's own estimated 1RM, and calling arithmetic
         AI is the kind of small lie that makes the honest claims harder to
         believe. --}}
    <x-panel :title="__('app.routines.progression_title')" :subtitle="__('app.routines.progression_sub')" class="mb-6">
        <p class="text-sm text-body mb-4">{{ __('app.routines.progression_body') }}</p>
        <form method="POST" action="{{ route('write.progression', $routine->hevy_id) }}">
            @csrf
            <button class="rounded-md bg-brand px-4 py-2 text-sm font-semibold text-on-fill hover:bg-brand-hover">
                {{ __('app.routines.stage') }}
            </button>
        </form>
    </x-panel>

    <x-panel :title="__('app.routines.prescription')">
        @foreach($routine->exercises as $ex)
            <div class="py-3 border-b border-subtle last:border-0">
                <div class="font-medium text-sm">{{ $ex->title }}</div>
                <div class="mt-1 flex flex-wrap gap-2">
                    @foreach($ex->sets ?? [] as $set)
                        <span class="text-xs rounded-sm bg-surface-sunk px-2 py-1">
                            {{ $set['type'] ?? 'normal' }}:
                            {{ isset($set['weight_kg']) ? units()->weight($set['weight_kg']).units()->weightUnit() : '—' }} × {{ $set['reps'] ?? ($set['rep_range']['start'] ?? '—') }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endforeach
    </x-panel>
</x-ui.page>
