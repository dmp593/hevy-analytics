@props(['items' => [], 'limit' => null, 'showLandmarks' => true])

@php
$rows = $limit ? array_slice($items, 0, $limit) : $items;

$barColor = fn (string $status) => [
    'optimal' => 'bg-good',
    'growth' => 'bg-grow',
    'maintenance' => 'bg-warn',
    'below_maintenance' => 'bg-bad',
    'junk' => 'bg-accent',
][$status] ?? 'bg-faint';
@endphp

<div class="space-y-2">
    @forelse($rows as $m)
        @php $l = $m['landmarks']; $pct = min(100, $m['per_week'] / max(1, $l['mrv']) * 100); @endphp
        <div>
            <div class="flex justify-between text-xs mb-0.5">
                <span class="font-medium">{{ \App\Support\Labels::muscle($m['muscle']) }}</span>
                <span class="text-muted">{{ __('app.volume_status.per_week', ['count' => $m['per_week']]) }} · <span>{{ \App\Support\Labels::volumeStatus($m['status']) }}</span></span>
            </div>
            <div class="h-2.5 rounded-sm bg-surface-sunk overflow-hidden">
                <div class="h-full {{ $barColor($m['status']) }}" style="width: {{ $pct }}%"></div>
            </div>
            @if($showLandmarks)
                <div class="text-[10px] text-faint mt-0.5">MV {{ $l['mv'] }} · MEV {{ $l['mev'] }} · MAV {{ $l['mav'] }} · MRV {{ $l['mrv'] }}</div>
            @endif
        </div>
    @empty
        <p class="text-sm text-muted">No sets in range.</p>
    @endforelse
</div>
