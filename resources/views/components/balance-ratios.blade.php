@props(['items' => []])

<div class="space-y-4">
    @foreach($items as $b)
        @php
            $total = ($b['value_a'] + $b['value_b']) ?: 1;
            $unit = __('app.balance.unit');
            $known = ($b['has_data'] ?? true) && $b['ratio'] !== null;
        @endphp
        <div>
            <div class="flex justify-between items-baseline gap-3 text-xs mb-1">
                <span>
                    {{ __('app.balance_groups.'.$b['label_a']) }} ({{ $b['value_a'] }}) vs {{ __('app.balance_groups.'.$b['label_b']) }} ({{ $b['value_b'] }})
                    @if($unit)<span class="text-faint">{{ $unit }}</span>@endif
                </span>
                @if($known)
                    {{-- Status is not colour-only: the word carries it too. --}}
                    <span class="font-semibold whitespace-nowrap {{ $b['balanced'] ? 'text-good' : 'text-warn' }}">
                        {{ $b['ratio'] }} · {{ $b['balanced'] ? __('app.balance.balanced') : __('app.balance.skewed') }}
                    </span>
                @else
                    <span class="text-muted whitespace-nowrap">{{ __('app.balance.not_enough') }}</span>
                @endif
            </div>
            @if($known)
                <div class="flex h-2 rounded-sm overflow-hidden bg-surface-sunk">
                    <div class="bg-brand" style="width: {{ $b['value_a'] / $total * 100 }}%"></div>
                    <div class="bg-info" style="width: {{ $b['value_b'] / $total * 100 }}%"></div>
                </div>
            @else
                <div class="h-2 rounded-sm bg-surface-sunk"></div>
            @endif
        </div>
    @endforeach
</div>
