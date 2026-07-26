<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800">Projections</h2></x-slot>

    <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <x-flash />
        <p class="text-sm text-gray-500 mb-6">Trend-based OLS regression over the last 12 months. Estimates are directional, not guarantees — check the R² quality badge. Lean mass, FFMI and strength use logarithmic dampening near natural ceilings.</p>

        <div class="grid lg:grid-cols-2 gap-6">
            @foreach($projections as $key => $proj)
                <x-panel :title="$proj['label']">
                    @php $d = $proj['data']; @endphp
                    @if(! ($d['available'] ?? false))
                        <p class="text-sm text-gray-500">{{ $d['reason'] ?? 'Not enough data.' }}</p>
                    @else
                        <div class="flex items-center gap-3 mb-3">
                            <span class="text-2xl font-bold">{{ $d['current'] }}</span>
                            <span class="text-xs px-2 py-0.5 rounded-full
                                {{ $d['quality']==='strong' ? 'bg-green-100 text-green-700' : ($d['quality']==='moderate' ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600') }}">
                                R² {{ $d['r2'] }} · {{ $d['quality'] }}{{ $d['dampened'] ? ' · dampened' : '' }}
                            </span>
                            <span class="text-xs text-gray-500">{{ $d['slope_per_week'] > 0 ? '+' : '' }}{{ $d['slope_per_week'] }}/wk</span>
                        </div>
                        <div class="grid grid-cols-4 gap-2 text-center">
                            @foreach($d['horizons'] as $h)
                                <div class="rounded-lg bg-gray-50 p-2">
                                    <div class="text-[10px] uppercase text-gray-500">{{ $h['label'] }}</div>
                                    <div class="text-sm font-bold">{{ $h['value'] }}</div>
                                    <div class="text-[10px] {{ $h['delta'] >= 0 ? 'text-green-600' : 'text-red-600' }}">{{ $h['delta'] >= 0 ? '+' : '' }}{{ $h['delta'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-panel>
            @endforeach
        </div>
    </div>
</x-app-layout>
