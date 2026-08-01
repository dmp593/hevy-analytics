<?php

namespace App\Services\Hevy;

use App\Models\ExerciseTemplate;
use App\Models\Routine;
use App\Models\RoutineExercise;
use App\Models\User;
use App\Science\Training\ExerciseChoices;
use App\Science\Volume\MuscleLandmarks;
use App\Services\Analytics\FilterCriteria;
use App\Services\Analytics\StrengthAnalytics;
use App\Services\Analytics\VolumeAnalytics;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Suggests SMALL adjustments to the routines the athlete actually uses.
 * Deterministic throughout — no model guesses anything:
 *
 *  - Add: a muscle below its RP MEV landmark (or untrained entirely) over the
 *    last four weeks gets one exercise slotted into the active routine that
 *    already trains its session neighbours. The need is the landmark; the
 *    exercise pick is curated convention (see ExerciseChoices).
 *  - Swap: an exercise whose e1RM shows a statistically REAL decline across
 *    eight weeks of maintained training earns a change of stimulus — same
 *    muscle, different movement. Variation as a plateau response has moderate
 *    evidence (Fonseca 2014; Baz-Valle 2019), and the card says "hypothesis",
 *    not "guarantee". Mere flatness never triggers this: the progression
 *    engine's back-off owns stalls; the swap is for genuine regression.
 *
 * Every suggestion becomes a staged write operation the user confirms —
 * nothing edits a routine on its own.
 */
class RoutineAdvisor
{
    /** A routine logged within this window counts as part of the active programme. */
    private const ACTIVE_ROUTINE_DAYS = 21;

    private const VOLUME_WINDOW_WEEKS = 4;

    private const TREND_WINDOW_WEEKS = 8;

    /** Six sessions in eight weeks also proves adherence — fewer and the "decline" may just be detraining. */
    private const DECLINE_MIN_SESSIONS = 6;

    /**
     * The fitted weekly decline must exceed TWICE its own standard error —
     * roughly 95% one-sided confidence that the slope is genuinely negative,
     * twice the bar the trend badges use for direction.
     */
    private const DECLINE_SE_MULTIPLE = 2.0;

    /** Small adjustments only: never more than this many cards at once. */
    private const MAX_SUGGESTIONS = 3;

    private const MAX_ADDITIONS = 2;

    /**
     * Hevy groups most programmes leave untrained on purpose (or cover
     * incidentally): suggesting neck work to everyone is noise, not advice.
     * They still appear with their landmarks on the muscle page.
     */
    private const OPTIONAL_GROUPS = ['neck', 'forearms', 'abductors', 'adductors'];

    public function __construct(private readonly User $user) {}

    /** @return array<int, array<string, mixed>> */
    public function suggestions(): array
    {
        $routines = $this->activeRoutines();
        if ($routines->isEmpty()) {
            return [];
        }

        $templates = $this->user->exerciseTemplates()->get();
        $muscleByTemplate = $templates->pluck('primary_muscle_group', 'hevy_id');

        $suggestions = [
            ...$this->additions($routines, $templates, $muscleByTemplate),
            ...$this->swaps($routines, $templates),
        ];

        return array_slice($suggestions, 0, self::MAX_SUGGESTIONS);
    }

    /**
     * Routines with a logged session in the window, most recent first, with
     * their prescription loaded. Suggestions target the programme being run,
     * not every routine ever created.
     *
     * @return Collection<int, Routine>
     */
    private function activeRoutines(): Collection
    {
        $lastUse = $this->user->workouts()
            ->whereNotNull('routine_hevy_id')
            ->where('start_time', '>=', Carbon::now()->subDays(self::ACTIVE_ROUTINE_DAYS))
            ->selectRaw('routine_hevy_id, max(start_time) as last_used')
            ->groupBy('routine_hevy_id')
            ->pluck('last_used', 'routine_hevy_id');

        if ($lastUse->isEmpty()) {
            return collect();
        }

        return $this->user->routines()->with('exercises')
            ->whereIn('hevy_id', $lastUse->keys())
            ->get()
            ->sortByDesc(fn ($r) => $lastUse[$r->hevy_id])
            ->values();
    }

    /**
     * @param  Collection<int, Routine>  $routines
     * @param  Collection<int, ExerciseTemplate>  $templates
     * @param  Collection<string, ?string>  $muscleByTemplate
     * @return array<int, array<string, mixed>>
     */
    private function additions(Collection $routines, Collection $templates, Collection $muscleByTemplate): array
    {
        $volume = new VolumeAnalytics($this->user, new FilterCriteria(
            from: Carbon::now()->subWeeks(self::VOLUME_WINDOW_WEEKS),
            to: Carbon::now(),
        ));

        $perWeek = collect($volume->weeklySetsPerMuscle())->pluck('per_week', 'muscle');

        // Every advisable muscle below its MEV, the furthest below first.
        // Muscles with zero sets are absent from the analytics output, which
        // is exactly the "never trained at all" case — they rank worst.
        $gaps = collect(MuscleLandmarks::GROUPS)
            ->reject(fn ($m) => in_array($m, self::OPTIONAL_GROUPS, true))
            ->map(fn ($m) => [
                'muscle' => $m,
                'per_week' => (float) ($perWeek[$m] ?? 0.0),
                'mev' => MuscleLandmarks::for($m)['mev'],
            ])
            ->filter(fn ($g) => $g['mev'] > 0 && $g['per_week'] < $g['mev'])
            ->sortBy(fn ($g) => $g['per_week'] / $g['mev'])
            ->values();

        $out = [];
        foreach ($gaps as $gap) {
            if (count($out) >= self::MAX_ADDITIONS) {
                break;
            }

            $routine = $this->bestRoutineFor($gap['muscle'], $routines, $muscleByTemplate);
            if (! $routine) {
                continue;
            }

            $template = ExerciseChoices::pickForMuscle(
                $templates,
                $gap['muscle'],
                $this->templateIdsIn($routine),
                $this->titlesIn($routine),
            );
            if (! $template) {
                continue;
            }

            $out[] = [
                'type' => 'add',
                'muscle' => $gap['muscle'],
                'per_week' => round($gap['per_week'], 1),
                'mev' => $gap['mev'],
                'routine_hevy_id' => $routine->hevy_id,
                'routine_title' => $routine->title,
                'template_hevy_id' => $template->hevy_id,
                'template_title' => $template->title,
            ];
        }

        return $out;
    }

    /**
     * The active routine whose session already trains the gap's neighbours —
     * calves go to the leg day, not the push day. No compatible session means
     * no suggestion: forcing calves into a chest routine helps nobody.
     *
     * @param  Collection<int, Routine>  $routines
     * @param  Collection<string, ?string>  $muscleByTemplate
     */
    private function bestRoutineFor(string $muscle, Collection $routines, Collection $muscleByTemplate): ?Routine
    {
        $related = [$muscle, ...ExerciseChoices::sessionNeighbours($muscle)];

        $best = null;
        $bestScore = 0;
        foreach ($routines as $routine) {
            $score = $routine->exercises
                ->filter(fn ($ex) => in_array($muscleByTemplate[$ex->exercise_template_hevy_id] ?? null, $related, true))
                ->count();

            // Strictly greater: routines arrive most-recently-used first, so
            // ties resolve to the one the athlete trained latest.
            if ($score > $bestScore) {
                $best = $routine;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /**
     * @param  Collection<int, Routine>  $routines
     * @param  Collection<int, ExerciseTemplate>  $templates
     * @return array<int, array<string, mixed>>
     */
    private function swaps(Collection $routines, Collection $templates): array
    {
        $trends = (new StrengthAnalytics($this->user, new FilterCriteria(
            from: Carbon::now()->subWeeks(self::TREND_WINDOW_WEEKS),
            to: Carbon::now(),
        )))->exerciseTrends();

        $out = [];
        foreach ($trends as $templateId => $trend) {
            if ($trend['direction'] !== 'down'
                || $trend['sessions'] < self::DECLINE_MIN_SESSIONS
                || $trend['pct_per_week'] > -self::DECLINE_SE_MULTIPLE * $trend['se_pct_per_week']) {
                continue;
            }

            // Only exercises the active programme still prescribes: a lift
            // that fell out of the routines needs no swap card.
            [$routine, $exercise] = $this->findInRoutines($routines, (string) $templateId);
            if (! $routine || ! $exercise) {
                continue;
            }

            $current = $templates->firstWhere('hevy_id', (string) $templateId);
            if (! $current) {
                continue;
            }

            $alternative = ExerciseChoices::alternativeFor(
                $templates,
                $current,
                $this->templateIdsIn($routine),
                $this->titlesIn($routine),
            );
            if (! $alternative) {
                continue;
            }

            $out[] = [
                'type' => 'swap',
                'routine_hevy_id' => $routine->hevy_id,
                'routine_title' => $routine->title,
                'template_hevy_id' => $current->hevy_id,
                'template_title' => $current->title,
                'alternative_hevy_id' => $alternative->hevy_id,
                'alternative_title' => $alternative->title,
                'pct_per_week' => $trend['pct_per_week'],
                'sessions' => $trend['sessions'],
                'weeks' => self::TREND_WINDOW_WEEKS,
            ];
        }

        return $out;
    }

    /**
     * Template ids already prescribed by a routine. Ids, not just titles:
     * a synced routine exercise may carry a null title, and an exclusion
     * that misses it suggests adding an exercise the routine already has.
     *
     * @return array<int, string>
     */
    private function templateIdsIn(Routine $routine): array
    {
        return $routine->exercises->pluck('exercise_template_hevy_id')->filter()->values()->all();
    }

    /** @return array<int, string> */
    private function titlesIn(Routine $routine): array
    {
        return $routine->exercises->pluck('title')->filter()->values()->all();
    }

    /**
     * @param  Collection<int, Routine>  $routines
     * @return array{0: ?Routine, 1: ?RoutineExercise}
     */
    private function findInRoutines(Collection $routines, string $templateHevyId): array
    {
        foreach ($routines as $routine) {
            $exercise = $routine->exercises->firstWhere('exercise_template_hevy_id', $templateHevyId);
            if ($exercise) {
                return [$routine, $exercise];
            }
        }

        return [null, null];
    }
}
