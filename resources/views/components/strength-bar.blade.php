@props(['eval' => null, 'exercise' => null, 'showThresholds' => true])

@if($eval && ($eval['available'] ?? false))
@php
    $levels = \App\Science\Strength\StrengthStandards::LEVELS;
    $boundaries = $eval['boundaries'];
    $pct = max(0, min(100, $eval['percentile']));
    // The bar is positioned with an inline width, so its colour has to be inline
    // too. Referencing the token rather than a literal hex keeps it flipping with
    // the theme — a fixed #9ca3af sat invisibly on the dark canvas.
    $fill = 'var(--tier-'.($eval['level_index'] + 1).')';
    $fillSoft = "color-mix(in oklab, $fill 18%, transparent)";
    $sexWord = __('app.levels.sex_'.(($eval['sex'] ?? 'male') === 'female' ? 'female' : 'male'));
    $edges = array_merge([0], $boundaries, [100]);
    $pops = $eval['populations'] ?? [];
    $populationWord = __('app.levels.population_'.match($eval['source'] ?? 'builtin') {
        'fitnessvolt' => isset($pops['gym']) ? 'gym' : 'competition',
        'openpowerlifting' => 'competition',
        default => 'none',
    });
    $fmt = fn($v) => rtrim(rtrim(number_format((float)$v, 1), '0'), '.');
@endphp

<div class="w-full">
    @if($exercise)
        <div class="flex items-center justify-between mb-1">
            <span class="text-sm font-medium">{{ $exercise }}</span>
            <span class="text-xs font-semibold px-2 py-0.5 rounded-full" style="background: {{ $fillSoft }}; color: {{ $fill }}">
                {{ __('app.levels.tier.'.$eval['level']) }}
            </span>
        </div>
    @endif

    <div class="relative h-4 rounded-full bg-surface-sunk overflow-hidden">
        <div class="h-full rounded-l-full transition-all" style="width: {{ $pct }}%; background: {{ $fill }};"></div>
        @foreach($boundaries as $b)
            <div class="absolute top-0 bottom-0 w-px bg-surface/80" style="left: {{ $b }}%;"></div>
        @endforeach
        <div class="absolute -top-0.5 h-5 w-0.5 bg-ink" style="left: calc({{ $pct }}% - 1px);"></div>
    </div>

    <div class="relative mt-1 h-4 text-[11px] text-faint">
        @foreach($levels as $i => $lvl)
            @php $centre = ($edges[$i] + $edges[$i+1]) / 2; @endphp
            <span class="absolute -translate-x-1/2 {{ $i === $eval['level_index'] ? 'text-ink font-semibold' : '' }}" style="left: {{ $centre }}%;">{{ __('app.levels.tier.'.$lvl) }}</span>
        @endforeach
    </div>

    <p class="mt-2 text-sm text-body">
        {!! __('app.levels.stronger_than', [
            'percent' => '<span class="font-bold" style="color: '.$fill.'">'.e($fmt($pct)).'%</span>',
            'sex' => e($sexWord),
            'population' => e($populationWord),
        ]) !!}
    </p>

    {{-- Dual populations (FitnessVolt) --}}
    @if(count($pops) > 1)
        <div class="mt-1 flex flex-wrap gap-2 text-[11px]">
            @if(isset($pops['gym']))
                <span class="rounded-sm bg-info-soft text-info px-2 py-0.5">{{ __('app.levels.pop_gym', ['percent' => $fmt($pops['gym']['percentile']).'%']) }}@if(!empty($pops['gym']['sample'])) <span class="text-info">({{ __('app.levels.sample', ['count' => number_format($pops['gym']['sample'])]) }})</span>@endif</span>
            @endif
            @if(isset($pops['verified']))
                <span class="rounded-sm bg-accent-soft text-accent px-2 py-0.5">{{ __('app.levels.pop_verified', ['percent' => $fmt($pops['verified']['percentile']).'%']) }}@if(!empty($pops['verified']['sample'])) <span class="opacity-75">({{ __('app.levels.sample', ['count' => number_format($pops['verified']['sample'])]) }})</span>@endif</span>
            @endif
        </div>
    @endif

    @if($showThresholds && !empty($eval['thresholds_kg']))
        <div class="mt-2 grid grid-cols-5 gap-1 text-center text-[11px]">
            @foreach($levels as $i => $lvl)
                <div class="rounded-sm bg-surface-sunk py-1">
                    <div class="text-faint">{{ __('app.levels.tier.'.$lvl) }}</div>
                    <div class="font-semibold text-body">{{ units()->weight($eval['thresholds_kg'][$i], 0) }}{{ units()->weightUnit() }}</div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="mt-1 flex items-center justify-between text-[11px] text-faint">
        <span>
            e1RM {{ units()->weight($eval['e1rm']) }}{{ units()->weightUnit() }} @ {{ units()->weight($eval['bodyweight']) }}{{ units()->weightUnit() }} · {{ $eval['ratio'] }}×BW
            @if(($eval['age_factor'] ?? 1) != 1) · {{ __('app.levels.age_factor', ['value' => $eval['age_factor']]) }}@endif
        </span>
        <span>
            {{-- Derived from the source key rather than carried as a label on the
                 assessment: a sentence built in a service is a sentence no
                 language file can reach. --}}
            {{ __('app.levels.source.'.($eval['source'] ?? 'builtin')) }}
            @if(!empty($eval['attribution']['url']))
                {{-- py + negative my: a thumbable hit area on a link that must
                     stay one metadata-line tall. --}}
                · <a href="{{ $eval['attribution']['url'] }}" target="_blank" rel="noopener" class="-my-3.5 inline-flex items-center py-3.5 underline">{{ $eval['attribution']['text'] ?? __('app.levels.source_link') }}</a>
            @endif
        </span>
    </div>
</div>
@endif
