<?php

namespace App\Services\Analytics;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Immutable filter/group-by criteria shared across all analytics endpoints.
 * Supports grouping by routine and routine->exercise as first-class dimensions.
 */
class FilterCriteria
{
    public function __construct(
        public ?Carbon $from = null,
        public ?Carbon $to = null,
        public ?string $routineHevyId = null,
        public ?string $exerciseTemplateHevyId = null,
        public ?string $muscle = null,
        public ?string $equipment = null,
        public bool $includeWarmups = false,
        public float $secondaryMuscleWeight = 0.5,
        public string $period = 'month', // week|month|quarter|semester|year
        /**
         * The zone the from/to dates were expressed in.
         *
         * Only used to interpret request input — a date typed into the filter
         * means a date in the athlete's day, not in UTC. Bucketing reads the
         * timezone off the User instead, because every analytics service already
         * holds one and threading it through seventeen construction sites is a
         * drift waiting to happen.
         */
        public string $timezone = 'UTC',
    ) {}

    public static function fromRequest(Request $request, ?string $timezone = null): self
    {
        // Query strings are attacker-controlled: a malformed date must not 500
        // the page, and the numeric weight must stay in a range the maths can
        // survive.
        $tz = $timezone ?: 'UTC';

        // A date typed into the filter means a date in the ATHLETE's day, so it
        // is parsed in their zone and then compared against UTC timestamps.
        $parse = function (?string $v) use ($tz): ?Carbon {
            if (! $v) {
                return null;
            }
            try {
                return Carbon::parse($v, $tz);
            } catch (\Throwable) {
                return null;
            }
        };

        $from = $parse($request->query('from'))?->startOfDay()
            ?: Carbon::now($tz)->subMonths(6)->startOfDay();
        $to = $parse($request->query('to'))?->endOfDay()
            ?: Carbon::now($tz)->endOfDay();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        $period = (string) $request->query('period', 'month');

        return new self(
            from: $from,
            to: $to,
            routineHevyId: $request->query('routine') ?: null,
            exerciseTemplateHevyId: $request->query('exercise') ?: null,
            muscle: $request->query('muscle') ?: null,
            equipment: $request->query('equipment') ?: null,
            includeWarmups: $request->boolean('include_warmups'),
            secondaryMuscleWeight: max(0.0, min(1.0, (float) ($request->query('secondary_weight') ?? 0.5))),
            period: in_array($period, PeriodService::PERIODS, true) ? $period : 'month',
            timezone: $tz,
        );
    }

    public function toArray(): array
    {
        return [
            'from' => $this->from?->toDateString(),
            'to' => $this->to?->toDateString(),
            'routine' => $this->routineHevyId,
            'exercise' => $this->exerciseTemplateHevyId,
            'muscle' => $this->muscle,
            'equipment' => $this->equipment,
            'include_warmups' => $this->includeWarmups,
            'secondary_weight' => $this->secondaryMuscleWeight,
            'period' => $this->period,
        ];
    }
}
