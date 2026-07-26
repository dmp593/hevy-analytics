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

    /** @return Collection<int, object> */
    public function rows(): Collection
    {
        return $this->builder()->get()->map(function ($row) {
            $row->secondary_muscle_groups = $row->secondary_muscle_groups
                ? (json_decode($row->secondary_muscle_groups, true) ?: [])
                : [];

            return $row;
        });
    }
}
