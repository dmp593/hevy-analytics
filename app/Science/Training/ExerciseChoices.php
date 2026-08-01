<?php

namespace App\Science\Training;

use App\Models\ExerciseTemplate;
use Illuminate\Support\Collection;

/**
 * Curated exercise selection for routine-adjustment suggestions.
 *
 * Two honesty rules govern this file:
 *
 *  1. The NEED for an adjustment is scientific (weekly sets below the RP MEV
 *     landmark, or a statistically real e1RM decline). WHICH exercise fills
 *     the gap is program-design convention, not a formula — the preference
 *     lists below follow standard prescription practice (NSCA Essentials of
 *     Strength Training; Schoenfeld, Science and Development of Muscle
 *     Hypertrophy) of one well-known movement per muscle, compound before
 *     isolation where both exist. The UI says "suggestion", never "optimal".
 *
 *  2. Never invent an exercise. Candidates come from the user's own synced
 *     Hevy catalogue, so anything suggested is something their app can log.
 *     No match → no suggestion.
 *
 * Fragments are lowercase substrings of Hevy's standard English titles;
 * matching is case-insensitive and survives suffixes like "(Barbell)".
 * Exclusions run on template id AND title: a synced routine exercise can
 * carry a null title, and an id never lies about identity.
 */
class ExerciseChoices
{
    /** Title fragments per muscle, in prescription-preference order. */
    private const PREFERRED = [
        'chest' => ['bench press', 'chest press', 'push up', 'chest fly'],
        'shoulders' => ['overhead press', 'shoulder press', 'lateral raise'],
        'triceps' => ['triceps pushdown', 'tricep pushdown', 'skullcrusher', 'triceps extension', 'tricep extension', 'dip'],
        'biceps' => ['bicep curl', 'biceps curl', 'hammer curl', 'ez bar curl'],
        'forearms' => ['wrist curl', 'reverse curl', 'farmer'],
        'lats' => ['lat pulldown', 'pull up', 'pullup', 'seated row', 'bent over row'],
        'upper_back' => ['seated row', 'bent over row', 'face pull', 'reverse fly'],
        'traps' => ['shrug', 'face pull', 'upright row'],
        'lower_back' => ['back extension', 'romanian deadlift', 'good morning'],
        'abdominals' => ['cable crunch', 'crunch', 'hanging leg raise', 'hanging knee raise', 'plank', 'ab wheel'],
        'quadriceps' => ['squat', 'leg press', 'leg extension', 'lunge'],
        'hamstrings' => ['romanian deadlift', 'leg curl', 'good morning'],
        'glutes' => ['hip thrust', 'romanian deadlift', 'glute kickback', 'bulgarian split squat'],
        'calves' => ['standing calf raise', 'calf raise', 'calf press'],
        'abductors' => ['hip abduction', 'lateral band walk'],
        'adductors' => ['hip adduction', 'sumo squat'],
        'neck' => ['neck curl', 'neck extension'],
    ];

    /**
     * Which muscles usually share a session with a given muscle, so a gap can
     * be slotted into the routine that already trains its neighbours. This is
     * split convention (push/pull/legs and upper/lower groupings), not
     * physiology: calves belong with the leg day, biceps with the pulling day.
     */
    private const SESSION_NEIGHBOURS = [
        'chest' => ['shoulders', 'triceps'],
        'shoulders' => ['chest', 'triceps', 'traps'],
        'triceps' => ['chest', 'shoulders'],
        'biceps' => ['lats', 'upper_back', 'forearms'],
        'forearms' => ['lats', 'upper_back', 'biceps'],
        'lats' => ['upper_back', 'biceps', 'traps'],
        'upper_back' => ['lats', 'biceps', 'traps'],
        'traps' => ['lats', 'upper_back', 'shoulders'],
        'lower_back' => ['hamstrings', 'glutes', 'lats', 'upper_back'],
        'abdominals' => ['quadriceps', 'hamstrings', 'chest', 'lower_back'],
        'quadriceps' => ['hamstrings', 'glutes', 'calves'],
        'hamstrings' => ['quadriceps', 'glutes', 'calves', 'lower_back'],
        'glutes' => ['quadriceps', 'hamstrings', 'calves'],
        'calves' => ['quadriceps', 'hamstrings', 'glutes'],
        'abductors' => ['quadriceps', 'hamstrings', 'glutes'],
        'adductors' => ['quadriceps', 'hamstrings', 'glutes'],
        'neck' => ['traps', 'shoulders'],
    ];

    /** @return array<int, string> */
    public static function sessionNeighbours(string $muscle): array
    {
        return self::SESSION_NEIGHBOURS[$muscle] ?? [];
    }

    /**
     * The best available template for a muscle from the user's own catalogue,
     * skipping anything already in the routine (by id or by title).
     *
     * @param  Collection<int, ExerciseTemplate>  $templates  the user's catalogue
     * @param  array<int, string>  $excludeIds  template hevy_ids already prescribed
     * @param  array<int, string>  $excludeTitles
     */
    public static function pickForMuscle(Collection $templates, string $muscle, array $excludeIds = [], array $excludeTitles = []): ?ExerciseTemplate
    {
        $candidates = $templates
            ->where('primary_muscle_group', $muscle)
            ->reject(fn ($t) => in_array($t->hevy_id, $excludeIds, true))
            ->reject(fn ($t) => self::titleMatchesAny($t->title, $excludeTitles));

        foreach (self::PREFERRED[$muscle] ?? [] as $fragment) {
            $hit = $candidates->first(fn ($t) => str_contains(mb_strtolower($t->title ?? ''), $fragment));
            if ($hit) {
                return $hit;
            }
        }

        // Nothing preferred matched: fall back to any standard-catalogue
        // exercise for the muscle rather than suggesting nothing at all.
        return $candidates->firstWhere('is_custom', false) ?? $candidates->first();
    }

    /**
     * A different stimulus for the same muscle: same primary muscle group,
     * different equipment where possible, never the same movement.
     *
     * @param  Collection<int, ExerciseTemplate>  $templates
     * @param  array<int, string>  $excludeIds  template hevy_ids already prescribed
     * @param  array<int, string>  $excludeTitles
     */
    public static function alternativeFor(Collection $templates, ExerciseTemplate $current, array $excludeIds = [], array $excludeTitles = []): ?ExerciseTemplate
    {
        if (! $current->primary_muscle_group) {
            return null;
        }

        $candidates = $templates
            ->where('primary_muscle_group', $current->primary_muscle_group)
            ->reject(fn ($t) => $t->hevy_id === $current->hevy_id)
            ->reject(fn ($t) => in_array($t->hevy_id, $excludeIds, true))
            ->reject(fn ($t) => self::titleMatchesAny($t->title, array_merge($excludeTitles, [$current->title])));

        // Different equipment first — a barbell lift that stopped moving is
        // better varied to a dumbbell/machine/cable pattern than to another
        // barbell variation of itself.
        $differentEquipment = $candidates->reject(
            fn ($t) => $current->equipment !== null && $t->equipment === $current->equipment
        );

        foreach ([$differentEquipment, $candidates] as $pool) {
            foreach (self::PREFERRED[$current->primary_muscle_group] ?? [] as $fragment) {
                $hit = $pool->first(fn ($t) => str_contains(mb_strtolower($t->title ?? ''), $fragment));
                if ($hit) {
                    return $hit;
                }
            }
            $fallback = $pool->firstWhere('is_custom', false) ?? $pool->first();
            if ($fallback) {
                return $fallback;
            }
        }

        return null;
    }

    /** @param array<int, string> $titles */
    private static function titleMatchesAny(?string $title, array $titles): bool
    {
        $needle = mb_strtolower($title ?? '');

        foreach ($titles as $t) {
            $other = mb_strtolower((string) $t);
            if ($needle !== '' && ($needle === $other || str_contains($other, $needle) || str_contains($needle, $other))) {
                return true;
            }
        }

        return false;
    }
}
