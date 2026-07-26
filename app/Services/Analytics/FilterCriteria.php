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
    ) {}

    public static function fromRequest(Request $request): self
    {
        // Query strings are attacker-controlled: a malformed date must not 500
        // the page, and the numeric weight must stay in a range the maths can
        // survive.
        $parse = function (?string $v): ?Carbon {
            if (! $v) {
                return null;
            }
            try {
                return Carbon::parse($v);
            } catch (\Throwable) {
                return null;
            }
        };

        $from = $parse($request->query('from')) ?: Carbon::now()->subMonths(6)->startOfDay();
        $to = $parse($request->query('to')) ?: Carbon::now()->endOfDay();

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
