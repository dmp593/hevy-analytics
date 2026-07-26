@props(['items' => [], 'limit' => null, 'showLandmarks' => true])

@php
$rows = $limit ? array_slice($items, 0, $limit) : $items;

$barColor = fn (string $status) => [
    'optimal' => 'bg-green-500',
    'growth' => 'bg-emerald-400',
    'maintenance' => 'bg-amber-400',
    'below_maintenance' => 'bg-red-400',
    'junk' => 'bg-purple-500',
][$status] ?? 'bg-gray-400';
@endphp

<div class="space-y-2">
    @forelse($rows as $m)
        @php $l = $m['landmarks']; $pct = min(100, $m['per_week'] / max(1, $l['mrv']) * 100); @endphp
        <div>
            <div class="flex justify-between text-xs mb-0.5">
                <span class="font-medium capitalize">{{ str_replace('_', ' ', $m['muscle']) }}</span>
                <span class="text-gray-500">{{ $m['per_week'] }}/wk · <span class="capitalize">{{ str_replace('_', ' ', $m['status']) }}</span></span>
            </div>
            <div class="h-2.5 rounded bg-gray-100 overflow-hidden">
                <div class="h-full {{ $barColor($m['status']) }}" style="width: {{ $pct }}%"></div>
            </div>
            @if($showLandmarks)
                <div class="text-[10px] text-gray-400 mt-0.5">MV {{ $l['mv'] }} · MEV {{ $l['mev'] }} · MAV {{ $l['mav'] }} · MRV {{ $l['mrv'] }}</div>
            @endif
        </div>
    @empty
        <p class="text-sm text-gray-500">No sets in range.</p>
    @endforelse
</div>
