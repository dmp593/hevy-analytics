<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The routines index answers "which Treino A is which?" — Hevy titles are
 * not unique, so the page groups by the athlete's own folders and puts the
 * newest routines (usually the active programme) first.
 */
class RoutinesPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['hevy_api_key' => 'test-key'])->save();

        return $user;
    }

    public function test_routines_group_by_folder_with_newest_folder_first(): void
    {
        $user = $this->makeUser();

        $user->routineFolders()->create(['hevy_id' => 'f-new', 'title' => 'PPL 2026']);
        $user->routineFolders()->create(['hevy_id' => 'f-old', 'title' => 'Plano antigo']);

        $user->routines()->create(['hevy_id' => 'r-old', 'title' => 'Treino A', 'folder_hevy_id' => 'f-old', 'hevy_created_at' => now()->subYear()]);
        $user->routines()->create(['hevy_id' => 'r-loose', 'title' => 'Avulso', 'hevy_created_at' => now()->subMonths(6)]);
        $user->routines()->create(['hevy_id' => 'r-b', 'title' => 'Treino B', 'folder_hevy_id' => 'f-new', 'hevy_created_at' => now()->subDays(3)]);
        $user->routines()->create(['hevy_id' => 'r-a', 'title' => 'Treino A', 'folder_hevy_id' => 'f-new', 'hevy_created_at' => now()->subDay()]);

        $response = $this->actingAs($user)->get(route('routines'));

        $response->assertOk();

        // The folder holding the newest routine leads; loose routines sit
        // under their own heading; the stale folder trails.
        $response->assertSeeInOrder(['PPL 2026', __('app.routines.no_folder'), 'Plano antigo']);

        // Inside a folder, newest first: Treino A (1 day old) before
        // Treino B (3 days old), despite the alphabetical order.
        $response->assertSeeInOrder(['Treino A', 'Treino B']);
    }

    public function test_page_explains_itself_via_the_help_disclosure(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->get(route('routines'))
            ->assertOk()
            ->assertSee(__('app.help.toggle'))
            ->assertSee(__('app.help.routines'));
    }
}
