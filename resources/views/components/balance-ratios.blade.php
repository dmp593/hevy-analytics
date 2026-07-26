@props(['items' => []])

<div class="space-y-4">
    @foreach($items as $b)
        @php $total = ($b['value_a'] + $b['value_b']) ?: 1; @endphp
        <div>
            <div class="flex justify-between text-xs mb-1">
                <span>{{ $b['label_a'] }} ({{ number_format($b['value_a']) }}) vs {{ $b['label_b'] }} ({{ number_format($b['value_b']) }})</span>
                <span class="{{ $b['balanced'] ? 'text-green-600' : 'text-amber-600' }} font-semibold">{{ $b['ratio'] ?? '—' }}</span>
            </div>
            <div class="flex h-2 rounded overflow-hidden bg-gray-100">
                <div class="bg-indigo-500" style="width: {{ $b['value_a'] / $total * 100 }}%"></div>
                <div class="bg-sky-300" style="width: {{ $b['value_b'] / $total * 100 }}%"></div>
            </div>
        </div>
    @endforeach
</div>
