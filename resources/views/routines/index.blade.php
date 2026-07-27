<x-ui.page :title="__('app.pages.routines')" :subtitle="__('app.pages.routines_sub')">
    <x-flash />

    <x-panel :title="__('app.routines.performance')" :subtitle="__('app.routines.performance_sub')" class="mb-6">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead><tr class="text-left text-xs uppercase text-muted border-b">
                    <th class="py-2 pr-4">{{ __('app.routines.routine') }}</th>
                    <th class="py-2 pr-4">{{ __('app.routines.sessions') }}</th>
                    <th class="py-2 pr-4">{{ __('app.routines.avg_tonnage') }}</th>
                    <th class="py-2 pr-4">{{ __('app.routines.progression') }}</th>
                    <th class="py-2 pr-4">{{ __('app.routines.last_performed') }}</th>
                    <th class="py-2 pr-4"></th>
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
                                <span class="ml-1 text-[10px] font-normal text-muted" title="{{ __('app.routines.noisy_tip') }}">{{ __('app.routines.noisy') }}</span>
                            @endif
                        </td>
                        <td class="py-2 pr-4 text-muted">{{ $o['last_performed'] }}</td>
                        <td class="py-2 pr-4"><a href="{{ route('routines.show', $o['routine_id']) }}" class="text-brand-ink">{{ __('app.routines.analyse_arrow') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-3 text-muted">{{ __('app.routines.none') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-panel>

    <x-panel :title="__('app.routines.all')">
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($routines as $r)
                <div class="rounded-lg border border-line p-4">
                    <div class="font-medium">{{ $r->title }}</div>
                    <div class="text-xs text-muted mb-3">{{ trans_choice('app.routines.exercise_count', $r->exercises_count, ['count' => $r->exercises_count]) }}</div>
                    <div class="flex gap-3 text-sm">
                        <a href="{{ route('routines.show', $r->hevy_id) }}" class="text-brand-ink">{{ __('app.routines.analyse') }}</a>
                        <a href="{{ route('routines.edit', $r->hevy_id) }}" class="text-body">{{ __('app.routines.edit') }}</a>
                    </div>
                </div>
            @endforeach
        </div>
    </x-panel>
</x-ui.page>
