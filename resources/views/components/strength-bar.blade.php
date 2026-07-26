@props(['eval' => null, 'exercise' => null, 'showThresholds' => true])

@if($eval && ($eval['available'] ?? false))
@php
    $levels = \App\Science\Strength\StrengthStandards::LEVELS;
    $boundaries = $eval['boundaries'];
    $pct = max(0, min(100, $eval['percentile']));
    $colors = ['#9ca3af', '#38bdf8', '#22c55e', '#f59e0b', '#a855f7'];
    $fill = $colors[$eval['level_index']];
    $sexWord = ($eval['sex'] ?? 'male') === 'female' ? 'female' : 'male';
    $edges = array_merge([0], $boundaries, [100]);
    $pops = $eval['populations'] ?? [];
    $populationWord = match($eval['source'] ?? 'builtin') {
        'fitnessvolt' => isset($pops['gym']) ? 'gym' : 'competition',
        'openpowerlifting' => 'competition',
        default => '',
    };
    $fmt = fn($v) => rtrim(rtrim(number_format((float)$v, 1), '0'), '.');
@endphp

<div class="w-full">
    @if($exercise)
        <div class="flex items-center justify-between mb-1">
            <span class="text-sm font-medium">{{ $exercise }}</span>
            <span class="text-xs font-semibold px-2 py-0.5 rounded-full" style="background: {{ $fill }}22; color: {{ $fill }}">
                {{ $eval['level'] }}
            </span>
        </div>
    @endif

    <div class="relative h-4 rounded-full bg-gray-100 overflow-hidden">
        <div class="h-full rounded-l-full transition-all" style="width: {{ $pct }}%; background: {{ $fill }};"></div>
        @foreach($boundaries as $b)
            <div class="absolute top-0 bottom-0 w-px bg-white/80" style="left: {{ $b }}%;"></div>
        @endforeach
        <div class="absolute -top-0.5 h-5 w-0.5 bg-gray-900" style="left: calc({{ $pct }}% - 1px);"></div>
    </div>

    <div class="relative mt-1 h-4 text-[10px] text-gray-400">
        @foreach($levels as $i => $lvl)
            @php $centre = ($edges[$i] + $edges[$i+1]) / 2; @endphp
            <span class="absolute -translate-x-1/2 {{ $i === $eval['level_index'] ? 'text-gray-900 font-semibold' : '' }}" style="left: {{ $centre }}%;">{{ $lvl }}</span>
        @endforeach
    </div>

    <p class="mt-2 text-sm text-gray-700">
        You are stronger than <span class="font-bold" style="color: {{ $fill }}">{{ $fmt($pct) }}%</span>
        of {{ $sexWord }} {{ $populationWord ? $populationWord.' ' : '' }}lifters your age and bodyweight.
    </p>

    {{-- Dual populations (FitnessVolt) --}}
    @if(count($pops) > 1)
        <div class="mt-1 flex flex-wrap gap-2 text-[11px]">
            @if(isset($pops['gym']))
                <span class="rounded-sm bg-sky-50 text-sky-700 px-2 py-0.5">Gym: {{ $fmt($pops['gym']['percentile']) }}%@if(!empty($pops['gym']['sample'])) <span class="text-sky-400">(n={{ number_format($pops['gym']['sample']) }})</span>@endif</span>
            @endif
            @if(isset($pops['verified']))
                <span class="rounded-sm bg-purple-50 text-purple-700 px-2 py-0.5">Verified/competition: {{ $fmt($pops['verified']['percentile']) }}%@if(!empty($pops['verified']['sample'])) <span class="text-purple-400">(n={{ number_format($pops['verified']['sample']) }})</span>@endif</span>
            @endif
        </div>
    @endif

    @if($showThresholds && !empty($eval['thresholds_kg']))
        <div class="mt-2 grid grid-cols-5 gap-1 text-center text-[10px]">
            @foreach($levels as $i => $lvl)
                <div class="rounded-sm bg-gray-50 py-1">
                    <div class="text-gray-400">{{ $lvl }}</div>
                    <div class="font-semibold text-gray-700">{{ $eval['thresholds_kg'][$i] }}kg</div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="mt-1 flex items-center justify-between text-[10px] text-gray-400">
        <span>
            e1RM {{ $eval['e1rm'] }}kg @ {{ $eval['bodyweight'] }}kg · {{ $eval['ratio'] }}×BW
            @if(($eval['age_factor'] ?? 1) != 1) · age ×{{ $eval['age_factor'] }}@endif
        </span>
        <span>
            {{ $eval['source_label'] ?? '' }}
            @if(!empty($eval['attribution']['url']))
                · <a href="{{ $eval['attribution']['url'] }}" target="_blank" rel="noopener" class="underline">{{ $eval['attribution']['text'] ?? 'source' }}</a>
            @endif
        </span>
    </div>
</div>
@endif
