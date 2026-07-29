@props(['rhythm'])

{{-- A GitHub-style training calendar: 26 weeks of columns, Monday-first
     rows, each cell shaded by that day's hard sets. Server-rendered divs
     on purpose — no chart library, no JS, dark-mode for free. --}}
@php
    $start = \Illuminate\Support\Carbon::parse($rhythm['from']);
    $today = \Illuminate\Support\Carbon::now(auth()->user()->resolvedTimezone())->toDateString();
    $max = max(1, $rhythm['max']);

    // Bucketing lives on TrainingRhythm so both calendar styles agree on
    // what "dark" means; this component only maps buckets to classes.
    $shade = fn (int $sets): string => match (\App\Services\Analytics\TrainingRhythm::bucket($sets, $max)) {
        'q4' => 'bg-brand',
        'q3' => 'bg-brand/70',
        'q2' => 'bg-brand/45',
        'q1' => 'bg-brand/25',
        default => 'bg-surface-sunk',
    };
@endphp

<div class="overflow-x-auto" role="img" aria-label="{{ __('app.rhythm.heatmap_aria') }}">
    <div class="flex gap-1" style="min-width: 640px">
        @for ($week = 0; $week < 26; $week++)
            <div class="flex flex-col gap-1">
                @for ($dow = 0; $dow < 7; $dow++)
                    @php
                        $date = $start->copy()->addWeeks($week)->addDays($dow)->toDateString();
                        $sets = $rhythm['days'][$date] ?? 0;
                    @endphp
                    @if ($date <= $today)
                        <div class="h-3 w-3 rounded-[3px] {{ $shade($sets) }}"
                             title="{{ $date }} · {{ trans_choice('app.rhythm.sets', $sets, ['count' => $sets]) }}"></div>
                    @else
                        <div class="h-3 w-3"></div>
                    @endif
                @endfor
            </div>
        @endfor
    </div>
</div>
