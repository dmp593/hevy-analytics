<?php

namespace App\Support;

/**
 * Resolves an exercise TITLE to the muscle groups the analytics count.
 *
 * Exists for imported data. The Hevy API hands every exercise a
 * primary_muscle_group, and the whole volume page hangs off that — but a CSV
 * export carries only titles. Without this, an imported account would show
 * every workout and an empty muscle page, which reads as "the import broke"
 * rather than "the file had no muscle column".
 *
 * Ordered keyword rules rather than a giant exact-name map, because users
 * rename exercises ("Bench Press (Larsen)", "Competition Squat") and an exact
 * map degrades to nothing the moment a title deviates. The EXACT map on top
 * exists only for names a keyword rule would get wrong.
 *
 * Every muscle key here must be one MuscleLandmarks::GROUPS knows, or the
 * volume page would file sets under a muscle that has no landmarks.
 */
class ExerciseMuscles
{
    /** Titles where keyword rules would misfire. Checked first, lowercased. */
    private const EXACT = [
        // "Rowing" the cardio machine, not a back row.
        'rowing (machine)' => null,
        'rowing machine' => null,
        // Pullover works the lats despite "over" containing no useful keyword.
        'pullover (dumbbell)' => ['lats', ['chest', 'triceps']],
        'pullover (machine)' => ['lats', ['chest', 'triceps']],
        // Upright rows hit delts, not the mid-back a "row" rule would pick.
        'upright row (barbell)' => ['shoulders', ['traps', 'biceps']],
        'upright row (dumbbell)' => ['shoulders', ['traps', 'biceps']],
        'upright row (cable)' => ['shoulders', ['traps', 'biceps']],
    ];

    /**
     * Ordered [keyword, primary, secondaries] rules. FIRST match wins, so the
     * specific must come before the general — 'leg curl' before 'curl',
     * 'romanian deadlift' before 'deadlift', 'reverse fly' before 'fly'.
     *
     * @var array<int, array{0: string, 1: ?string, 2: array<int, string>}>
     */
    private const RULES = [
        // ---- cardio and conditioning: real training, no hypertrophy muscle.
        ['running', null, []],
        ['jogging', null, []],
        ['cycling', null, []],
        ['spinning', null, []],
        ['elliptical', null, []],
        ['stair', null, []],
        ['jump rope', null, []],
        ['swimming', null, []],
        ['walking', null, []],
        ['treadmill', null, []],
        ['battle rope', null, []],
        ['sled', null, []],

        // ---- hamstrings before generic curl/deadlift rules.
        ['leg curl', 'hamstrings', ['calves']],
        ['nordic', 'hamstrings', ['glutes']],
        ['good morning', 'hamstrings', ['lower_back', 'glutes']],
        ['romanian deadlift', 'hamstrings', ['glutes', 'lower_back']],
        ['stiff leg', 'hamstrings', ['glutes', 'lower_back']],
        ['glute ham raise', 'hamstrings', ['glutes']],

        // ---- glutes before squat/bridge generics.
        ['hip thrust', 'glutes', ['hamstrings']],
        ['glute bridge', 'glutes', ['hamstrings']],
        ['glute kickback', 'glutes', ['hamstrings']],
        ['hip abduction', 'abductors', ['glutes']],
        ['abduction', 'abductors', ['glutes']],
        ['hip adduction', 'adductors', []],
        ['adduction', 'adductors', []],
        ['copenhagen', 'adductors', ['abdominals']],

        // ---- back: the specific pulls before generic 'pull'.
        ['face pull', 'upper_back', ['shoulders', 'traps']],
        ['lat pulldown', 'lats', ['biceps', 'upper_back']],
        ['pulldown', 'lats', ['biceps', 'upper_back']],
        ['pull up', 'lats', ['biceps', 'upper_back']],
        ['pull-up', 'lats', ['biceps', 'upper_back']],
        ['chin up', 'lats', ['biceps']],
        ['chin-up', 'lats', ['biceps']],
        ['muscle up', 'lats', ['triceps', 'chest']],
        ['reverse fly', 'upper_back', ['shoulders']],
        ['rear delt', 'shoulders', ['upper_back']],
        ['shrug', 'traps', ['upper_back']],
        ['row', 'upper_back', ['lats', 'biceps']],

        // ---- lower back before generic deadlift keeps SLDL/RDL correct above.
        ['back extension', 'lower_back', ['glutes', 'hamstrings']],
        ['hyperextension', 'lower_back', ['glutes', 'hamstrings']],
        ['superman', 'lower_back', ['glutes']],
        ['deadlift', 'lower_back', ['hamstrings', 'glutes', 'traps']],
        ['rack pull', 'lower_back', ['traps', 'hamstrings']],

        // ---- shoulders before the bench/press generics.
        ['overhead press', 'shoulders', ['triceps']],
        ['military press', 'shoulders', ['triceps']],
        ['shoulder press', 'shoulders', ['triceps']],
        ['arnold press', 'shoulders', ['triceps']],
        ['lateral raise', 'shoulders', []],
        ['front raise', 'shoulders', []],
        ['push press', 'shoulders', ['triceps', 'quadriceps']],
        ['landmine press', 'shoulders', ['chest', 'triceps']],

        // ---- triceps before 'press'/'extension' generics.
        ['skullcrusher', 'triceps', []],
        ['skull crusher', 'triceps', []],
        ['tricep', 'triceps', []],
        ['pushdown', 'triceps', []],
        ['close grip bench', 'triceps', ['chest', 'shoulders']],
        ['jm press', 'triceps', ['chest']],

        // ---- chest.
        ['bench press', 'chest', ['triceps', 'shoulders']],
        ['chest press', 'chest', ['triceps', 'shoulders']],
        ['chest fly', 'chest', ['shoulders']],
        ['pec deck', 'chest', ['shoulders']],
        ['cable crossover', 'chest', ['shoulders']],
        ['push up', 'chest', ['triceps', 'shoulders']],
        ['push-up', 'chest', ['triceps', 'shoulders']],
        ['dip', 'chest', ['triceps', 'shoulders']],
        ['fly', 'chest', ['shoulders']],

        // ---- forearms before biceps' generic curl.
        ['wrist curl', 'forearms', []],
        ['reverse curl', 'forearms', ['biceps']],
        ['farmer', 'forearms', ['traps']],
        ['dead hang', 'forearms', ['lats']],
        ['grip', 'forearms', []],

        // ---- biceps.
        ['hammer curl', 'biceps', ['forearms']],
        ['preacher curl', 'biceps', []],
        ['curl', 'biceps', ['forearms']],

        // ---- quads: specifics, then squat/press generics.
        ['leg extension', 'quadriceps', []],
        ['leg press', 'quadriceps', ['glutes', 'hamstrings']],
        ['hack squat', 'quadriceps', ['glutes']],
        ['front squat', 'quadriceps', ['glutes', 'abdominals']],
        ['split squat', 'quadriceps', ['glutes']],
        ['lunge', 'quadriceps', ['glutes']],
        ['step up', 'quadriceps', ['glutes']],
        ['step-up', 'quadriceps', ['glutes']],
        ['pistol', 'quadriceps', ['glutes']],
        ['goblet squat', 'quadriceps', ['glutes']],
        ['squat', 'quadriceps', ['glutes', 'hamstrings']],
        ['wall sit', 'quadriceps', []],

        // ---- calves.
        ['calf', 'calves', []],
        ['tibialis', 'calves', []],

        // ---- abs.
        ['crunch', 'abdominals', []],
        ['sit up', 'abdominals', []],
        ['sit-up', 'abdominals', []],
        ['plank', 'abdominals', []],
        ['leg raise', 'abdominals', []],
        ['knee raise', 'abdominals', []],
        ['russian twist', 'abdominals', []],
        ['ab wheel', 'abdominals', []],
        ['ab roller', 'abdominals', []],
        ['dead bug', 'abdominals', []],
        ['hollow', 'abdominals', []],
        ['woodchop', 'abdominals', []],
        ['pallof', 'abdominals', []],
        ['l-sit', 'abdominals', []],

        // ---- neck.
        ['neck', 'neck', []],

        // ---- whole-body lifts, filed under their biggest mover.
        ['clean and jerk', 'quadriceps', ['shoulders', 'glutes', 'traps']],
        ['power clean', 'quadriceps', ['glutes', 'traps']],
        ['clean', 'quadriceps', ['glutes', 'traps']],
        ['snatch', 'quadriceps', ['shoulders', 'traps']],
        ['thruster', 'quadriceps', ['shoulders', 'glutes']],
        ['kettlebell swing', 'glutes', ['hamstrings', 'lower_back']],
        ['burpee', 'quadriceps', ['chest', 'abdominals']],

        // ---- generic press LAST: everything specific already matched.
        ['press', 'shoulders', ['triceps']],
    ];

    /**
     * @return array{0: ?string, 1: array<int, string>} [primary, secondaries];
     *                                                  a null primary means "counts as training, not as hypertrophy volume".
     */
    public static function resolve(string $title): array
    {
        $needle = mb_strtolower(trim($title));

        if (array_key_exists($needle, self::EXACT)) {
            $hit = self::EXACT[$needle];

            return $hit === null ? [null, []] : [$hit[0], $hit[1]];
        }

        foreach (self::RULES as [$keyword, $primary, $secondaries]) {
            if (str_contains($needle, $keyword)) {
                return [$primary, $secondaries];
            }
        }

        return [null, []];
    }
}
