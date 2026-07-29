@props(['rhythm'])

{{-- The classic reading of the same data: the last three months as real
     calendar pages, day numbers and all, trained days shaded. For people
     to whom a contribution grid says nothing. --}}
@php
    $tz = auth()->user()->resolvedTimezone();
    $today = \Illuminate\Support\Carbon::now($tz);
    $max = max(1, $rhythm['max']);

    // Same buckets as the contribution grid — TrainingRhythm::bucket() is
    // the single source; only the class mapping differs.
    $shade = fn (int $sets): string => match (\App\Services\Analytics\TrainingRhythm::bucket($sets, $max)) {
        'q4' => 'bg-brand text-on-fill',
        'q3' => 'bg-brand/70 text-on-fill',
        'q2' => 'bg-brand/45 text-ink',
        'q1' => 'bg-brand/25 text-ink',
        default => '',
    };
@endphp

<div class="grid gap-4 sm:grid-cols-3">
    @for ($offset = 2; $offset >= 0; $offset--)
        @php
            $month = $today->copy()->startOfMonth()->subMonths($offset);
            $firstDow = $month->dayOfWeekIso; // 1 = Monday
        @endphp
        <div>
            <p class="mb-1 text-xs font-semibold text-ink">{{ $month->isoFormat('MMMM YYYY') }}</p>
            <div class="grid grid-cols-7 gap-0.5 text-center text-[10px] text-faint">
                @foreach (range(0, 6) as $d)
                    <div class="py-0.5">{{ $today->copy()->startOfWeek()->addDays($d)->isoFormat('dd') }}</div>
                @endforeach
                @for ($blank = 1; $blank < $firstDow; $blank++)
                    <div></div>
                @endfor
                @for ($day = 1; $day <= $month->daysInMonth; $day++)
                    @php
                        $date = $month->copy()->day($day)->toDateString();
                        $sets = $rhythm['days'][$date] ?? 0;
                    @endphp
                    <div class="rounded py-0.5 text-[11px] {{ $date > $today->toDateString() ? 'text-faint/50' : ($sets > 0 ? $shade($sets) : 'text-muted') }}"
                         @if ($sets > 0) title="{{ $date }} · {{ trans_choice('app.rhythm.sets', $sets, ['count' => $sets]) }}" @endif>
                        {{ $day }}
                    </div>
                @endfor
            </div>
        </div>
    @endfor
</div>
