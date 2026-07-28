<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The platform converter. The preview (loss manifest) is free; the download
 * is paid. Every writer is validated two ways: exact cells, and a round-trip
 * back through our own parser — a conversion that our own importer cannot
 * read is wrong by definition.
 */
class ConvertTest extends TestCase
{
    use RefreshDatabase;

    /** Trial accounts see the whole product, converter included. */
    private function athlete(): User
    {
        return User::factory()->create(['timezone' => 'Europe/Lisbon']);
    }

    private function seedHistory(User $user): void
    {
        $csv = implode("\n", [
            'title,start_time,exercise_title,set_index,set_type,weight_kg,reps,duration_seconds,distance_km,rpe,exercise_notes',
            '"Push Day","2026-07-20 18:30","Bench Press (Barbell)",0,warmup,40,12,,,,"felt strong"',
            '"Push Day","2026-07-20 18:30","Bench Press (Barbell)",1,normal,100,5,,,8.5,',
            '"Push Day","2026-07-20 18:30","Running",0,normal,,,1800,5,,',
        ]);

        $this->actingAs($user)->post('/import', [
            'file' => UploadedFile::fake()->createWithContent('w.csv', $csv),
        ]);
    }

    private function download(User $user, string $target): string
    {
        return $this->actingAs($user)->post('/convert/download', [
            'mode' => 'account', 'target' => $target,
        ])->assertOk()->getContent() ?: '';
    }

    public function test_the_preview_counts_losses_from_the_real_rows(): void
    {
        $user = $this->athlete();
        $this->seedHistory($user);

        $response = $this->actingAs($user)->post('/convert/preview', [
            'mode' => 'account', 'target' => 'fitnotes',
        ])->assertOk();

        $preview = $response->viewData('preview');
        $losses = collect($preview['losses'])->keyBy('key');

        $this->assertSame(1, $preview['workouts']);
        $this->assertSame(3, $preview['sets']);
        // FitNotes keeps dates only, no titles, no RPE, no types, no notes.
        $this->assertSame(1, $losses['time']['count']);
        $this->assertSame(1, $losses['title']['count']);
        $this->assertSame(1, $losses['set_types']['count']); // the warmup
        $this->assertSame(1, $losses['rpe']['count']);
        $this->assertSame(1, $losses['notes']['count']);
        $this->assertArrayNotHasKey('cardio', $losses->all()); // FitNotes carries cardio
    }

    public function test_converting_to_hevy_loses_nothing_and_round_trips(): void
    {
        $user = $this->athlete();
        $this->seedHistory($user);

        $preview = $this->actingAs($user)->post('/convert/preview', [
            'mode' => 'account', 'target' => 'hevy',
        ])->assertOk()->viewData('preview');

        $this->assertSame([], $preview['losses']);

        $csv = $this->download($user, 'hevy');

        // Round-trip: a fresh account importing the converted file must end
        // with identical numbers.
        $other = $this->athlete();
        $this->actingAs($other)->post('/import', [
            'file' => UploadedFile::fake()->createWithContent('converted.csv', $csv),
        ])->assertRedirect(route('dashboard'));

        $this->assertSame(1, $other->workouts()->count());
        $workout = $other->workouts()->firstOrFail();
        $this->assertSame('Push Day', $workout->title);
        // 18:30 Lisbon in July = 17:30 UTC — the round-trip must not shift it.
        $this->assertSame('2026-07-20 17:30', $workout->start_time->format('Y-m-d H:i'));

        $bench = $workout->exercises()->where('title', 'Bench Press (Barbell)')->firstOrFail();
        $sets = $bench->sets()->orderBy('index')->get();
        $this->assertSame('warmup', $sets[0]->type);
        $this->assertEqualsWithDelta(100.0, (float) $sets[1]->weight_kg, 0.01);
        $this->assertEqualsWithDelta(8.5, (float) $sets[1]->rpe, 0.01);
    }

    public function test_the_strong_writer_speaks_strongs_dialect(): void
    {
        $user = $this->athlete();
        $this->seedHistory($user);

        $csv = $this->download($user, 'strong');
        $lines = explode("\n", trim($csv));

        $this->assertSame('Date,"Workout Name","Exercise Name","Set Order",Weight,"Weight Unit",Reps,RPE,Distance,"Distance Unit",Seconds,Notes,"Workout Notes"', $lines[0]);
        // Warmup becomes W1; the working set numbers from 1; weights carry kg.
        $this->assertStringContainsString('W1', $lines[1]);
        $this->assertStringContainsString(',40,kg,12', $lines[1]);
        $this->assertStringContainsString(',100,kg,5,8.5', $lines[2]);
        // And our own parser reads it straight back.
        $other = $this->athlete();
        $this->actingAs($other)->post('/import', [
            'file' => UploadedFile::fake()->createWithContent('strong.csv', $csv),
        ]);
        $this->assertSame(1, $other->workouts()->count());
        $this->assertStringContainsString('Strong', session('status'));
    }

    public function test_the_fitnotes_writer_derives_categories_and_times(): void
    {
        $user = $this->athlete();
        $this->seedHistory($user);

        $csv = $this->download($user, 'fitnotes');

        $this->assertStringContainsString('Date,Exercise,Category,"Weight (kg)",Reps,Distance,"Distance Unit",Time', $csv);
        $this->assertStringContainsString('"Bench Press (Barbell)",Chest,100,5', $csv);
        // The cardio row: 5 km and 0:30:00, category Cardio.
        $this->assertStringContainsString('Running,Cardio,,,5,km,0:30:00', $csv);
        // Dates only — the Lisbon evening stays on its own day.
        $this->assertStringContainsString('2026-07-20', $csv);
    }

    public function test_the_jefit_writer_packs_sets_and_drops_pure_cardio(): void
    {
        $user = $this->athlete();
        $this->seedHistory($user);

        $preview = $this->actingAs($user)->post('/convert/preview', [
            'mode' => 'account', 'target' => 'jefit',
        ])->assertOk()->viewData('preview');

        $losses = collect($preview['losses'])->keyBy('key');
        $this->assertSame(1, $losses['cardio']['count']);

        $csv = $this->download($user, 'jefit');

        $this->assertStringContainsString('"40x12,100x5"', $csv);
        $this->assertStringNotContainsString('Running', $csv); // unsayable in this dialect
    }

    public function test_file_mode_converts_without_touching_the_account(): void
    {
        Storage::fake('local');
        $user = $this->athlete();

        $strongCsv = implode("\n", [
            'Date,Workout Name,Exercise Name,Set Order,Weight,Weight Unit,Reps',
            '"2026-07-01 09:00:00","Legs","Squat (Barbell)","1","120","kg","5"',
        ]);

        $this->actingAs($user)->post('/convert/preview', [
            'mode' => 'file', 'target' => 'hevy',
            'file' => UploadedFile::fake()->createWithContent('strong.csv', $strongCsv),
        ])->assertOk()->assertViewHas('preview');

        $this->assertSame(0, $user->workouts()->count());

        // The download re-reads the parked copy — no re-upload needed.
        $csv = $this->actingAs($user)->post('/convert/download', [
            'mode' => 'file', 'target' => 'hevy',
        ])->assertOk()->getContent() ?: '';

        $this->assertStringContainsString('Squat (Barbell)', $csv);
        $this->assertSame(0, $user->workouts()->count());
    }

    public function test_the_preview_is_free_but_the_download_is_paid(): void
    {
        $user = User::factory()->free()->create();
        $user->workouts()->create([
            'hevy_id' => 'x1', 'title' => 'Push', 'start_time' => now()->subDay(),
        ]);

        $this->actingAs($user)->post('/convert/preview', [
            'mode' => 'account', 'target' => 'strong',
        ])->assertOk()->assertSee(__('app.convert.paywall_title'));

        $this->actingAs($user)->post('/convert/download', [
            'mode' => 'account', 'target' => 'strong',
        ])->assertForbidden();
    }
}
