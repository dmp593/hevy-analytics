<x-ui.page :title="__('app.pages.projections')" :subtitle="__('app.pages.projections_sub')">
    <x-flash />

    <p class="mb-6 text-sm text-muted">{{ __('app.projections.method') }}</p>

    <div class="grid gap-6 lg:grid-cols-2">
        @foreach ($projections as $key => $proj)
            <x-ui.card :title="$proj['label']">
                @php $d = $proj['data']; @endphp

                @if (! ($d['available'] ?? false))
                    <p class="text-sm text-muted">{{ $d['reason'] ?? __('app.projections.not_enough') }}</p>
                @else
                    <div class="mb-3 flex flex-wrap items-center gap-3">
                        <span class="text-2xl font-bold text-ink">{{ $d['current'] }}</span>

                        <span @class([
                            'rounded-full px-2 py-0.5 text-xs',
                            'bg-good-soft text-good' => $d['quality'] === 'strong',
                            'bg-warn-soft text-warn' => $d['quality'] === 'moderate',
                            'bg-surface-sunk text-body' => ! in_array($d['quality'], ['strong', 'moderate'], true),
                        ])>
                            R² {{ $d['r2'] }} · {{ __('app.projections.quality_'.$d['quality']) }}
                        </span>

                        @if ($d['dampened'])
                            <span class="rounded-full bg-surface-sunk px-2 py-0.5 text-xs text-body"
                                  title="{{ __('app.projections.dampened_tip') }}">{{ __('app.projections.dampened') }}</span>
                        @endif

                        <span class="text-xs text-muted">{{ $d['slope_per_week'] > 0 ? '+' : '' }}{{ $d['slope_per_week'] }}/{{ __('app.projections.week_abbr') }}</span>
                    </div>

                    <div class="grid grid-cols-4 gap-2 text-center">
                        @foreach ($d['horizons'] as $h)
                            @php
                                // Whether a rise is good depends on the metric, not on its
                                // sign. Colouring every increase green told athletes their
                                // projected body fat and waist growth was a good outcome.
                                $tone = match (true) {
                                    $proj['rising'] === null => 'text-muted',
                                    $h['delta'] == 0 => 'text-muted',
                                    ($h['delta'] > 0) === ($proj['rising'] === 'good') => 'text-good',
                                    default => 'text-bad',
                                };
                            @endphp
                            <div class="rounded-lg bg-surface-sunk p-2">
                                <div class="text-[11px] uppercase text-muted">{{ $h['label'] }}</div>
                                <div class="text-sm font-bold text-ink">{{ $h['value'] }}</div>
                                <div class="text-[11px] {{ $tone }}">{{ $h['delta'] >= 0 ? '+' : '' }}{{ $h['delta'] }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>
        @endforeach
    </div>
</x-ui.page>
