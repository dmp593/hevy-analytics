<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsTrainingData;
use Tests\TestCase;

/**
 * The Cmd/Ctrl+K palette: a global overlay on every signed-in page, fed by a
 * static index (pages, actions) in the component and a per-account endpoint
 * (routines + performed exercises).
 */
class PaletteTest extends TestCase
{
    use RefreshDatabase, SeedsTrainingData;

    public function test_palette_ships_on_signed_in_pages_with_its_hint(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-command-palette', false)
            ->assertSee(__('app.palette.placeholder'))
            ->assertSee(__('app.palette.open_aria'));
    }

    public function test_data_endpoint_requires_auth(): void
    {
        $this->get(route('palette.data'))->assertRedirect(route('login'));
    }

    public function test_data_endpoint_returns_routines_and_performed_exercises(): void
    {
        $user = $this->makeAthlete();

        $user->routineFolders()->create(['hevy_id' => 'f1', 'title' => 'PPL']);
        $user->routines()->create(['hevy_id' => 'r1', 'title' => 'Treino A', 'folder_hevy_id' => 'f1']);

        $this->seedExerciseTemplates($user, [
            'bp' => ['Bench Press (Barbell)', 'chest', []],
            'sq' => ['Squat (Barbell)', 'quadriceps', []],
        ]);

        // Only the bench has history — the squat stays out of the palette.
        DB::table('workout_set_rollups')->insert([
            'user_id' => $user->id,
            'local_date' => now()->toDateString(),
            'exercise_title' => 'Bench Press (Barbell)',
            'exercise_template_hevy_id' => 'bp',
            'primary_muscle_group' => 'chest',
            'sets' => 3, 'reps' => 24, 'tonnage' => 1920,
            'best_weight' => 80, 'best_reps' => 8,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $data = $this->actingAs($user)->get(route('palette.data'))->assertOk()->json();

        $this->assertSame('Treino A', $data['routines'][0]['title']);
        $this->assertSame('PPL', $data['routines'][0]['context']);
        $this->assertStringContainsString('/routines/r1', $data['routines'][0]['url']);

        $titles = array_column($data['exercises'], 'title');
        $this->assertContains('Bench Press (Barbell)', $titles);
        $this->assertNotContains('Squat (Barbell)', $titles);
        $this->assertStringContainsString('exercise=bp', $data['exercises'][0]['url']);
    }

    public function test_data_endpoint_falls_back_to_the_catalogue_without_history(): void
    {
        $user = $this->makeAthlete();
        $this->seedExerciseTemplates($user, ['bp' => ['Bench Press (Barbell)', 'chest', []]]);

        $data = $this->actingAs($user)->get(route('palette.data'))->assertOk()->json();

        $this->assertContains('Bench Press (Barbell)', array_column($data['exercises'], 'title'));
    }

    public function test_data_endpoint_never_leaks_another_account(): void
    {
        $mine = $this->makeAthlete();
        $theirs = $this->makeAthlete();
        $theirs->routines()->create(['hevy_id' => 'r-theirs', 'title' => 'Rotina alheia']);

        $data = $this->actingAs($mine)->get(route('palette.data'))->assertOk()->json();

        $this->assertSame([], $data['routines']);
    }
}
