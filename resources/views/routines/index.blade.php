<x-ui.page :title="__('app.pages.routines')" :subtitle="__('app.pages.routines_sub')">
    <x-flash />

    <x-panel title="Routine performance" subtitle="Volume progression across sessions (6 months)" class="mb-6">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead><tr class="text-left text-xs uppercase text-muted border-b">
                    <th class="py-2 pr-4">Routine</th><th class="py-2 pr-4">Sessions</th>
                    <th class="py-2 pr-4">Avg tonnage</th><th class="py-2 pr-4">Progression</th>
                    <th class="py-2 pr-4">Last performed</th><th class="py-2 pr-4"></th>
                </tr></thead>
                <tbody>
                @forelse($overview as $o)
                    <tr class="border-b border-subtle">
                        <td class="py-2 pr-4 font-medium">{{ $o['routine'] }}</td>
                        <td class="py-2 pr-4">{{ $o['sessions'] }}</td>
                        <td class="py-2 pr-4">{{ number_format($o['avg_tonnage']) }} kg</td>
                        <td class="py-2 pr-4 {{ ($o['progression_pct'] ?? 0) >= 0 ? 'text-good' : 'text-bad' }}">
                            {{ $o['progression_pct'] !== null ? ($o['progression_pct'] > 0 ? '+' : '').$o['progression_pct'].'%' : '—' }}
                            {{-- A trend can be real but scattered. Saying so beats implying precision we do not have. --}}
                            @if($o['progression_pct'] !== null && ! ($o['progression_reliable'] ?? true))
                                <span class="ml-1 text-[10px] font-normal text-muted" title="Session-to-session tonnage varies a lot here, so treat the direction as a hint rather than a measurement.">noisy</span>
                            @endif
                        </td>
                        <td class="py-2 pr-4 text-muted">{{ $o['last_performed'] }}</td>
                        <td class="py-2 pr-4"><a href="{{ route('routines.show', $o['routine_id']) }}" class="text-brand-ink">Analyse →</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-3 text-muted">No routine sessions found. Sync your Hevy data.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-panel>

    <x-panel title="All routines">
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($routines as $r)
                <div class="rounded-lg border border-line p-4">
                    <div class="font-medium">{{ $r->title }}</div>
                    <div class="text-xs text-muted mb-3">{{ $r->exercises_count }} exercises</div>
                    <div class="flex gap-3 text-sm">
                        <a href="{{ route('routines.show', $r->hevy_id) }}" class="text-brand-ink">Analyse</a>
                        <a href="{{ route('routines.edit', $r->hevy_id) }}" class="text-body">Edit / progress</a>
                    </div>
                </div>
            @endforeach
        </div>
    </x-panel>
</x-ui.page>
