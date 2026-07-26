<x-ui.page :title="__('app.pages.projections')" :subtitle="__('app.pages.projections_sub')">
    <x-flash />
    <p class="text-sm text-muted mb-6">Trend-based OLS regression over the last 12 months. Estimates are directional, not guarantees — check the R² quality badge. Lean mass, FFMI and strength use logarithmic dampening near natural ceilings.</p>

    <div class="grid lg:grid-cols-2 gap-6">
        @foreach($projections as $key => $proj)
            <x-panel :title="$proj['label']">
                @php $d = $proj['data']; @endphp
                @if(! ($d['available'] ?? false))
                    <p class="text-sm text-muted">{{ $d['reason'] ?? 'Not enough data.' }}</p>
                @else
                    <div class="flex items-center gap-3 mb-3">
                        <span class="text-2xl font-bold">{{ $d['current'] }}</span>
                        <span class="text-xs px-2 py-0.5 rounded-full
                            {{ $d['quality']==='strong' ? 'bg-good-soft text-good' : ($d['quality']==='moderate' ? 'bg-warn-soft text-warn' : 'bg-surface-sunk text-body') }}">
                            R² {{ $d['r2'] }} · {{ $d['quality'] }}{{ $d['dampened'] ? ' · dampened' : '' }}
                        </span>
                        <span class="text-xs text-muted">{{ $d['slope_per_week'] > 0 ? '+' : '' }}{{ $d['slope_per_week'] }}/wk</span>
                    </div>
                    <div class="grid grid-cols-4 gap-2 text-center">
                        @foreach($d['horizons'] as $h)
                            <div class="rounded-lg bg-surface-sunk p-2">
                                <div class="text-[10px] uppercase text-muted">{{ $h['label'] }}</div>
                                <div class="text-sm font-bold">{{ $h['value'] }}</div>
                                <div class="text-[10px] {{ $h['delta'] >= 0 ? 'text-good' : 'text-bad' }}">{{ $h['delta'] >= 0 ? '+' : '' }}{{ $h['delta'] }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-panel>
        @endforeach
    </div>
</x-ui.page>
