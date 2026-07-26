<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800">Routines</h2></x-slot>

    <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <x-flash />

        <x-panel title="Routine performance" subtitle="Volume progression across sessions (6 months)" class="mb-6">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead><tr class="text-left text-xs uppercase text-gray-500 border-b">
                        <th class="py-2 pr-4">Routine</th><th class="py-2 pr-4">Sessions</th>
                        <th class="py-2 pr-4">Avg tonnage</th><th class="py-2 pr-4">Progression</th>
                        <th class="py-2 pr-4">Last performed</th><th class="py-2 pr-4"></th>
                    </tr></thead>
                    <tbody>
                    @forelse($overview as $o)
                        <tr class="border-b border-gray-50">
                            <td class="py-2 pr-4 font-medium">{{ $o['routine'] }}</td>
                            <td class="py-2 pr-4">{{ $o['sessions'] }}</td>
                            <td class="py-2 pr-4">{{ number_format($o['avg_tonnage']) }} kg</td>
                            <td class="py-2 pr-4 {{ ($o['progression_pct'] ?? 0) >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                {{ $o['progression_pct'] !== null ? ($o['progression_pct'] > 0 ? '+' : '').$o['progression_pct'].'%' : '—' }}
                                {{-- A trend can be real but scattered. Saying so beats implying precision we do not have. --}}
                                @if($o['progression_pct'] !== null && ! ($o['progression_reliable'] ?? true))
                                    <span class="ml-1 text-[10px] font-normal text-gray-500" title="Session-to-session tonnage varies a lot here, so treat the direction as a hint rather than a measurement.">noisy</span>
                                @endif
                            </td>
                            <td class="py-2 pr-4 text-gray-500">{{ $o['last_performed'] }}</td>
                            <td class="py-2 pr-4"><a href="{{ route('routines.show', $o['routine_id']) }}" class="text-indigo-600">Analyse →</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-3 text-gray-500">No routine sessions found. Sync your Hevy data.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-panel>

        <x-panel title="All routines">
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($routines as $r)
                    <div class="rounded-lg border border-gray-200 p-4">
                        <div class="font-medium">{{ $r->title }}</div>
                        <div class="text-xs text-gray-500 mb-3">{{ $r->exercises_count }} exercises</div>
                        <div class="flex gap-3 text-sm">
                            <a href="{{ route('routines.show', $r->hevy_id) }}" class="text-indigo-600">Analyse</a>
                            <a href="{{ route('routines.edit', $r->hevy_id) }}" class="text-gray-600">Edit / progress</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-panel>
    </div>
</x-app-layout>
