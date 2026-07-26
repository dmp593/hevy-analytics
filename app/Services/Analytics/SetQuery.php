<?php

namespace App\Services\Analytics;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds a flat, joined query of workout sets with exercise/workout/template
 * context, applying FilterCriteria. Every analytics service consumes this.
 */
class SetQuery
{
    /** @var array<string, Collection<int, object>> */
    private static array $memo = [];

    public function __construct(
        private readonly User $user,
        private readonly FilterCriteria $filter,
    ) {}

    public function builder(): Builder
    {
        $q = DB::table('workout_sets as s')
            ->join('workout_exercises as we', 's.workout_exercise_id', '=', 'we.id')
            ->join('workouts as w', 'we.workout_id', '=', 'w.id')
            ->leftJoin('exercise_templates as t', function ($join) {
                $join->on('t.hevy_id', '=', 'we.exercise_template_hevy_id')
                    ->on('t.user_id', '=', 'w.user_id');
            })
            ->where('w.user_id', $this->user->id)
            ->select([
                's.id as set_id',
                's.type',
                's.weight_kg',
                's.reps',
                's.rpe',
                's.duration_seconds',
                's.distance_meters',
                'we.id as workout_exercise_id',
                'we.title as exercise_title',
                'we.exercise_template_hevy_id',
                'w.id as workout_id',
                'w.hevy_id as workout_hevy_id',
                'w.title as workout_title',
                'w.start_time',
                'w.routine_hevy_id',
                't.primary_muscle_group',
                't.secondary_muscle_groups',
                't.equipment',
            ]);

        if ($this->filter->from) {
            $q->where('w.start_time', '>=', $this->filter->from);
        }
        if ($this->filter->to) {
            $q->where('w.start_time', '<=', $this->filter->to);
        }
        if ($this->filter->routineHevyId) {
            $q->where('w.routine_hevy_id', $this->filter->routineHevyId);
        }
        if ($this->filter->exerciseTemplateHevyId) {
            $q->where('we.exercise_template_hevy_id', $this->filter->exerciseTemplateHevyId);
        }
        if ($this->filter->equipment) {
            $q->where('t.equipment', $this->filter->equipment);
        }
        if ($this->filter->muscle) {
            $muscle = $this->filter->muscle;
            $q->where(function ($sub) use ($muscle) {
                $sub->where('t.primary_muscle_group', $muscle)
                    ->orWhere('t.secondary_muscle_groups', 'like', '%"'.$muscle.'"%');
            });
        }
        if (! $this->filter->includeWarmups) {
            $q->where('s.type', '!=', 'warmup');
        }

        return $q->orderBy('w.start_time');
    }

    /**
     * @return Collection<int, object>
     *
     * Memoised for the lifetime of the request. Analytics services each build
     * their own SetQuery from the same user and filter, so a single dashboard
     * render was re-running this join six or seven times. The cache key is the
     * user plus the filter, so different windows still get their own result.
     */
    public function rows(): Collection
    {
        $key = $this->cacheKey();

        if (isset(self::$memo[$key])) {
            return self::$memo[$key];
        }

        return self::$memo[$key] = $this->builder()->get()->map(function ($row) {
            $row->secondary_muscle_groups = $row->secondary_muscle_groups
                ? (json_decode($row->secondary_muscle_groups, true) ?: [])
                : [];

            return $row;
        });
    }

    /**
     * Keyed on the full filter, at full timestamp precision.
     *
     * FilterCriteria::toArray() is for display and renders dates as Y-m-d, which
     * would make "today up to 09:00" and "today up to 23:59" the same key and
     * hand one window the other's rows.
     */
    private function cacheKey(): string
    {
        return $this->user->id.':'.md5(serialize([
            $this->filter->from?->toIso8601String(),
            $this->filter->to?->toIso8601String(),
            $this->filter->routineHevyId,
            $this->filter->exerciseTemplateHevyId,
            $this->filter->muscle,
            $this->filter->equipment,
            $this->filter->includeWarmups,
        ]));
    }

    /**
     * Drop the in-request cache. Only needed by tests and long-running workers,
     * where "the request" spans more than one logical read.
     */
    public static function flushMemo(): void
    {
        self::$memo = [];
    }
}
