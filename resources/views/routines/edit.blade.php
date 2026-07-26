<x-ui.page :title="__('app.pages.routine_edit', ['name' => \$routine->title])" width="4xl">
    <x-flash />

    <x-panel title="AI-assisted progression" subtitle="Evidence-based double progression pushed to Hevy" class="mb-6">
        <p class="text-sm text-body mb-4">
            Generates an updated routine using double progression (add a rep until 12, then +2.5 kg and reset),
            informed by your recent estimated 1RM per exercise. It is staged as a pending write operation —
            you review the exact changes and confirm before anything is pushed to Hevy.
        </p>
        <form method="POST" action="{{ route('write.progression', $routine->hevy_id) }}">
            @csrf
            <button class="rounded-md bg-brand px-4 py-2 text-sm font-semibold text-on-fill hover:bg-brand-hover">
                Stage progression
            </button>
        </form>
    </x-panel>

    <x-panel title="Current prescription">
        @foreach($routine->exercises as $ex)
            <div class="py-3 border-b border-subtle last:border-0">
                <div class="font-medium text-sm">{{ $ex->title }}</div>
                <div class="mt-1 flex flex-wrap gap-2">
                    @foreach($ex->sets ?? [] as $set)
                        <span class="text-xs rounded-sm bg-surface-sunk px-2 py-1">
                            {{ $set['type'] ?? 'normal' }}:
                            {{ $set['weight_kg'] ?? '—' }}kg × {{ $set['reps'] ?? ($set['rep_range']['start'] ?? '—') }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endforeach
    </x-panel>
</x-ui.page>
