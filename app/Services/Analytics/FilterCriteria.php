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
        $parse = fn (?string $v) => $v ? Carbon::parse($v) : null;

        return new self(
            from: $parse($request->query('from')) ?: Carbon::now()->subMonths(6)->startOfDay(),
            to: $parse($request->query('to')) ?: Carbon::now()->endOfDay(),
            routineHevyId: $request->query('routine') ?: null,
            exerciseTemplateHevyId: $request->query('exercise') ?: null,
            muscle: $request->query('muscle') ?: null,
            equipment: $request->query('equipment') ?: null,
            includeWarmups: $request->boolean('include_warmups'),
            secondaryMuscleWeight: (float) ($request->query('secondary_weight') ?? 0.5),
            period: $request->query('period', 'month'),
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
