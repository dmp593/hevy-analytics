<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The check-in comparison: 2-4 dates, photos aligned by pose, and a weight
 * delta judged against the active goal — the only judged number on the page.
 */
class CompareTest extends TestCase
{
    use RefreshDatabase;

    private function athlete(): User
    {
        return User::factory()->create();
    }

    private function measure(User $user, string $date, array $fields): void
    {
        $user->bodyMeasurements()->create(['date' => $date] + $fields);
    }

    private function photo(User $user, string $date, string $pose): void
    {
        $user->progressPhotos()->create([
            'date' => $date, 'angle' => $pose, 'path' => "p/{$date}-{$pose}.jpg",
        ]);
    }

    private function weightRow($response): array
    {
        return collect($response->viewData('comparison')['rows'])
            ->firstWhere('label', __('app.body.measure.weight'));
    }

    public function test_the_picker_lists_dates_that_have_photos_or_measurements(): void
    {
        $user = $this->athlete();
        $this->photo($user, '2026-06-01', 'front');
        $this->measure($user, '2026-07-01', ['weight_kg' => 80]);

        $response = $this->actingAs($user)->get('/compare')->assertOk();

        $dates = $response->viewData('candidates')->pluck('date');

        $this->assertTrue($dates->contains('2026-06-01'));
        $this->assertTrue($dates->contains('2026-07-01'));
        $this->assertNull($response->viewData('comparison'));
    }

    public function test_photos_align_by_pose_and_the_oldest_date_is_the_baseline(): void
    {
        $user = $this->athlete();

        foreach (['front', 'back'] as $pose) {
            $this->photo($user, '2026-05-01', $pose);
        }
        $this->photo($user, '2026-07-01', 'front');
        $this->measure($user, '2026-05-01', ['weight_kg' => 80]);
        $this->measure($user, '2026-07-01', ['weight_kg' => 82]);

        // Newest ticked first: the baseline must still be the oldest.
        $response = $this->actingAs($user)
            ->get('/compare?dates[]=2026-07-01&dates[]=2026-05-01')->assertOk();

        $comparison = $response->viewData('comparison');

        $this->assertSame('2026-05-01', $comparison['columns'][0]['date']);
        $this->assertSame(['front', 'back'], $comparison['poses']->all());

        // The newer column shows the delta against the baseline.
        $weight = $this->weightRow($response);
        $this->assertSame('+2.0', $weight['cells'][1]['delta']);
        $this->assertSame('↑', $weight['cells'][1]['arrow']);
    }

    public function test_weight_gain_is_green_on_a_bulk_and_red_on_a_cut(): void
    {
        foreach (['lean_bulk' => 'good', 'cut' => 'bad'] as $goalType => $expected) {
            $user = $this->athlete();
            Goal::factory()->for($user)->create(['type' => $goalType]);
            $this->measure($user, '2026-05-01', ['weight_kg' => 80]);
            $this->measure($user, '2026-07-01', ['weight_kg' => 83]);

            $response = $this->actingAs($user)
                ->get('/compare?dates[]=2026-05-01&dates[]=2026-07-01')->assertOk();

            $this->assertSame($expected, $this->weightRow($response)['cells'][1]['tone'], $goalType);
        }
    }

    public function test_a_change_inside_the_noise_band_is_stable_not_judged(): void
    {
        $user = $this->athlete();
        Goal::factory()->for($user)->create(['type' => 'lean_bulk']);
        $this->measure($user, '2026-06-01', ['weight_kg' => 80]);
        $this->measure($user, '2026-06-08', ['weight_kg' => 80.4]); // within 1%

        $response = $this->actingAs($user)
            ->get('/compare?dates[]=2026-06-01&dates[]=2026-06-08')->assertOk();

        $this->assertSame('warn', $this->weightRow($response)['cells'][1]['tone']);
    }

    public function test_maintenance_judges_both_directions_equally(): void
    {
        foreach ([79.0 => 'bad', 80.2 => 'good', 82.5 => 'bad'] as $newWeight => $expected) {
            $user = $this->athlete();
            Goal::factory()->for($user)->create(['type' => 'recomposition']);
            $this->measure($user, '2026-06-01', ['weight_kg' => 80]);
            $this->measure($user, '2026-07-01', ['weight_kg' => $newWeight]);

            $response = $this->actingAs($user)
                ->get('/compare?dates[]=2026-06-01&dates[]=2026-07-01')->assertOk();

            $this->assertSame($expected, $this->weightRow($response)['cells'][1]['tone'], (string) $newWeight);
        }
    }

    public function test_without_a_goal_every_delta_is_neutral(): void
    {
        $user = $this->athlete();
        $this->measure($user, '2026-05-01', ['weight_kg' => 80, 'waist' => 85]);
        $this->measure($user, '2026-07-01', ['weight_kg' => 85, 'waist' => 88]);

        $response = $this->actingAs($user)
            ->get('/compare?dates[]=2026-05-01&dates[]=2026-07-01')->assertOk();

        foreach ($response->viewData('comparison')['rows'] as $row) {
            foreach ($row['cells'] as $cell) {
                $this->assertSame('neutral', $cell['tone']);
            }
        }
    }

    public function test_girths_are_never_judged_even_with_a_goal(): void
    {
        $user = $this->athlete();
        Goal::factory()->for($user)->create(['type' => 'lean_bulk']);
        $this->measure($user, '2026-05-01', ['weight_kg' => 80, 'waist' => 85]);
        $this->measure($user, '2026-07-01', ['weight_kg' => 83, 'waist' => 88]);

        $response = $this->actingAs($user)
            ->get('/compare?dates[]=2026-05-01&dates[]=2026-07-01')->assertOk();

        $waist = collect($response->viewData('comparison')['rows'])
            ->firstWhere('label', __('app.body.measure.waist'));

        $this->assertSame('neutral', $waist['cells'][1]['tone']);
    }

    public function test_more_than_four_dates_are_rejected(): void
    {
        $user = $this->athlete();

        $query = collect(range(1, 5))
            ->map(fn ($i) => "dates[]=2026-06-0{$i}")->implode('&');

        $this->actingAs($user)->get("/compare?{$query}")
            ->assertSessionHasErrors('dates');
    }

    public function test_an_imperial_athlete_reads_the_table_in_pounds(): void
    {
        $user = User::factory()->create(['unit_system' => 'imperial']);
        $this->measure($user, '2026-05-01', ['weight_kg' => 80]);
        $this->measure($user, '2026-07-01', ['weight_kg' => 82]);

        $response = $this->actingAs($user)
            ->get('/compare?dates[]=2026-05-01&dates[]=2026-07-01')->assertOk();

        $weight = $this->weightRow($response);

        $this->assertSame('lb', $weight['unit']);
        $this->assertEqualsWithDelta(176.4, $weight['cells'][0]['display'], 0.1);
        $this->assertSame('+4.4', $weight['cells'][1]['delta']);
    }
}
