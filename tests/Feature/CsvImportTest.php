<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkoutSet;
use App\Support\ExerciseMuscles;
use App\Support\Onboarding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * The CSV import is the door for Hevy accounts without an API key — Hevy only
 * issues keys to Pro subscribers, the CSV export exists on every account. So
 * this path has to hold up against real-world files: pounds, odd dates,
 * blank rows, re-uploads of overlapping exports.
 */
class CsvImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'title,start_time,end_time,description,exercise_title,superset_id,exercise_notes,set_index,set_type,weight_kg,reps,distance_km,duration_seconds,rpe';

    private function athlete(): User
    {
        return User::factory()->create(['timezone' => 'Europe/Lisbon']);
    }

    private function upload(User $user, string $csv)
    {
        return $this->actingAs($user)->post('/import', [
            'file' => UploadedFile::fake()->createWithContent('workouts.csv', $csv),
        ]);
    }

    private function sampleCsv(): string
    {
        return implode("\n", [
            self::HEADER,
            '"Push Day","27 Jul 2025, 18:30","27 Jul 2025, 19:40",,"Bench Press (Barbell)",,,0,warmup,40,12,,,',
            '"Push Day","27 Jul 2025, 18:30","27 Jul 2025, 19:40",,"Bench Press (Barbell)",,,1,normal,80,8,,,8.5',
            '"Push Day","27 Jul 2025, 18:30","27 Jul 2025, 19:40",,"Bench Press (Barbell)",,,2,normal,80,7,,,9',
            '"Push Day","27 Jul 2025, 18:30","27 Jul 2025, 19:40",,"Lateral Raise (Dumbbell)",,,0,normal,10,15,,,',
            '"Pull Day","25 Jul 2025, 18:00","25 Jul 2025, 19:10",,"Lat Pulldown (Cable)",,,0,normal,55,10,,,',
            '"Pull Day","25 Jul 2025, 18:00","25 Jul 2025, 19:10",,"Seated Cable Row",,,0,normal,60,10,,,',
        ]);
    }

    public function test_a_hevy_export_imports_workouts_exercises_and_sets(): void
    {
        $user = $this->athlete();

        $this->upload($user, $this->sampleCsv())
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status');

        $this->assertSame(2, $user->workouts()->count());

        $push = $user->workouts()->where('title', 'Push Day')->firstOrFail();

        $this->assertSame(2, $push->exercises()->count());

        $bench = $push->exercises()->where('title', 'Bench Press (Barbell)')->firstOrFail();

        $this->assertSame(3, $bench->sets()->count());
        $this->assertSame('warmup', $bench->sets()->orderBy('index')->first()->type);
        $this->assertEqualsWithDelta(8.5, (float) $bench->sets()->where('index', 1)->first()->rpe, 0.01);

        // 18:30 in Lisbon (BST, +01:00 in July) must not be stored as 18:30 UTC
        // — that shift is what silently moves evening workouts to the wrong day.
        $this->assertSame('2025-07-27 17:30', $push->start_time->format('Y-m-d H:i'));
    }

    /** The muscle page must work from a file that carries no muscle column. */
    public function test_imported_exercises_carry_inferred_muscle_groups(): void
    {
        $user = $this->athlete();
        $this->upload($user, $this->sampleCsv());

        $muscles = $user->exerciseTemplates()->pluck('primary_muscle_group', 'title');

        $this->assertSame('chest', $muscles['Bench Press (Barbell)']);
        $this->assertSame('shoulders', $muscles['Lateral Raise (Dumbbell)']);
        $this->assertSame('lats', $muscles['Lat Pulldown (Cable)']);
        $this->assertSame('upper_back', $muscles['Seated Cable Row']);
    }

    /**
     * The property that lets people upload fearlessly: the same file — or a
     * fresh export overlapping the old one — merges instead of duplicating.
     */
    public function test_reimporting_the_same_file_duplicates_nothing(): void
    {
        $user = $this->athlete();

        $this->upload($user, $this->sampleCsv());
        $this->upload($user, $this->sampleCsv());

        $this->assertSame(2, $user->workouts()->count());
        $this->assertSame(4, WorkoutSet::whereHas(
            'workoutExercise.workout', fn ($q) => $q->where('user_id', $user->id)->where('title', 'Push Day'),
        )->count());
    }

    /** An account whose Hevy units are pounds produces weight_lbs columns. */
    public function test_pounds_are_converted_to_kilograms(): void
    {
        $user = $this->athlete();

        $csv = implode("\n", [
            'title,start_time,exercise_title,set_index,set_type,weight_lbs,reps',
            '"Push","2025-07-27 18:30","Bench Press (Barbell)",0,normal,225,5',
        ]);

        $this->upload($user, $csv);

        $set = WorkoutSet::whereHas(
            'workoutExercise.workout', fn ($q) => $q->where('user_id', $user->id),
        )->firstOrFail();

        $this->assertEqualsWithDelta(102.06, (float) $set->weight_kg, 0.01);
    }

    public function test_unreadable_rows_are_reported_not_silently_dropped(): void
    {
        $user = $this->athlete();

        $csv = implode("\n", [
            self::HEADER,
            '"Push Day","27 Jul 2025, 18:30",,,"Bench Press (Barbell)",,,0,normal,80,8,,,',
            '"Broken","not a date at all §§",,,"Bench Press (Barbell)",,,0,normal,80,8,,,',
        ]);

        $this->upload($user, $csv)->assertSessionHas('status', fn ($s) => str_contains($s, '1'));

        $this->assertSame(1, $user->workouts()->count());
    }

    public function test_a_file_that_is_not_a_workout_export_fails_loudly(): void
    {
        $user = $this->athlete();

        $this->upload($user, "name,email\nAlice,alice@example.test")
            ->assertSessionHas('error');

        $this->assertSame(0, $user->workouts()->count());
    }

    /**
     * A template the API already created must be reused, not shadowed: Hevy's
     * own muscle attribution beats keyword inference, and one id per exercise
     * keeps the per-exercise history unified.
     */
    public function test_an_existing_api_template_wins_over_a_synthetic_one(): void
    {
        $user = $this->athlete();

        $user->exerciseTemplates()->create([
            'hevy_id' => 'API123',
            'title' => 'Bench Press (Barbell)',
            'primary_muscle_group' => 'chest',
            'secondary_muscle_groups' => ['triceps'],
        ]);

        $this->upload($user, $this->sampleCsv());

        $bench = $user->workouts()->where('title', 'Push Day')->firstOrFail()
            ->exercises()->where('title', 'Bench Press (Barbell)')->firstOrFail();

        $this->assertSame('API123', $bench->exercise_template_hevy_id);
        $this->assertSame(0, $user->exerciseTemplates()->where('hevy_id', 'like', 'csv:bench%')->count());
    }

    public function test_imports_stay_inside_the_uploading_account(): void
    {
        $user = $this->athlete();
        $bystander = User::factory()->create();

        $this->upload($user, $this->sampleCsv());

        $this->assertSame(0, $bystander->workouts()->count());
        $this->assertSame(0, $bystander->exerciseTemplates()->count());
    }

    public function test_the_import_completes_the_data_onboarding_steps(): void
    {
        $user = $this->athlete();

        $steps = fn () => collect(Onboarding::for($user->fresh())->steps)
            ->keyBy('key')->map(fn ($s) => $s['done']);

        $this->assertFalse($steps()['hevy_key']);

        $this->upload($user, $this->sampleCsv());

        $this->assertTrue($steps()['hevy_key']);
        $this->assertTrue($steps()['sync']);
    }

    /**
     * A Strong-shaped file: different header names, a unit-less Weight column
     * with the unit beside it, warmups marked "W1" in Set Order, and no
     * set_type at all. One parser, several apps' dialects — this is the whole
     * multi-source strategy, since no other lifting app has a public API.
     */
    public function test_a_strong_shaped_export_imports_cleanly(): void
    {
        $user = $this->athlete();

        $csv = implode("\n", [
            'Date,Workout Name,Duration,Exercise Name,Set Order,Weight,Weight Unit,Reps,RPE,Notes',
            '"2025-07-20 09:00:00","Morning Push","1h 5m","Bench Press (Barbell)","W1","135","lbs","10","",""',
            '"2025-07-20 09:00:00","Morning Push","1h 5m","Bench Press (Barbell)","1","225","lbs","5","8",""',
            '"2025-07-20 09:00:00","Morning Push","1h 5m","Lateral Raise (Dumbbell)","1","20","lbs","15","",""',
        ]);

        $this->upload($user, $csv)->assertRedirect(route('dashboard'))->assertSessionHas('status');

        $workout = $user->workouts()->where('title', 'Morning Push')->firstOrFail();
        $bench = $workout->exercises()->where('title', 'Bench Press (Barbell)')->firstOrFail();

        $sets = $bench->sets()->orderBy('index')->get();

        $this->assertSame('warmup', $sets[0]->type);
        $this->assertSame('normal', $sets[1]->type);
        // 225 lbs → 102.06 kg: the unit column, not the header, carried it.
        $this->assertEqualsWithDelta(102.06, (float) $sets[1]->weight_kg, 0.01);
    }

    /** European Excel and some apps write semicolons; a comma parser sees one giant column. */
    public function test_a_semicolon_delimited_file_is_sniffed_and_parsed(): void
    {
        $user = $this->athlete();

        $csv = implode("\n", [
            'Date;Workout Name;Exercise Name;Set Order;Weight;Weight Unit;Reps',
            '2025-07-21 18:00:00;Legs;Squat (Barbell);1;100;kg;5',
            '2025-07-21 18:00:00;Legs;Squat (Barbell);2;100;kg;5',
        ]);

        $this->upload($user, $csv)->assertSessionHas('status');

        $workout = $user->workouts()->where('title', 'Legs')->firstOrFail();

        $this->assertSame(2, WorkoutSet::whereHas(
            'workoutExercise', fn ($q) => $q->where('workout_id', $workout->id),
        )->count());
        $this->assertSame('quadriceps', $user->exerciseTemplates()->where('title', 'Squat (Barbell)')->value('primary_muscle_group'));
    }

    /** Spot checks on the classifier, including the traps in the rule order. */
    public function test_the_muscle_classifier_resolves_the_awkward_names(): void
    {
        $cases = [
            'Romanian Deadlift (Barbell)' => 'hamstrings',
            'Deadlift (Barbell)' => 'lower_back',
            'Leg Curl (Machine)' => 'hamstrings',
            'Hammer Curl (Dumbbell)' => 'biceps',
            'Reverse Curl (Barbell)' => 'forearms',
            'Face Pull (Cable)' => 'upper_back',
            'Pull Up' => 'lats',
            'Reverse Fly (Machine)' => 'upper_back',
            'Chest Fly (Dumbbell)' => 'chest',
            'Front Squat (Barbell)' => 'quadriceps',
            'Hip Thrust (Barbell)' => 'glutes',
            'Standing Calf Raise' => 'calves',
            'Skullcrusher (Barbell)' => 'triceps',
            'Upright Row (Barbell)' => 'shoulders',
            'Kettlebell Swing' => 'glutes',
        ];

        foreach ($cases as $title => $expected) {
            [$primary] = ExerciseMuscles::resolve($title);
            $this->assertSame($expected, $primary, $title);
        }

        // Cardio counts as training, never as hypertrophy volume for a muscle.
        [$primary] = ExerciseMuscles::resolve('Running');
        $this->assertNull($primary);
        [$primary] = ExerciseMuscles::resolve('Rowing (Machine)');
        $this->assertNull($primary);
    }
}
