<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Steps and sleep from health-app CSVs: hourly rows sum per day, a health
 * import never touches nutrition or weight, and units follow the header.
 */
class HealthCsvImportTest extends TestCase
{
    use RefreshDatabase;

    private function upload(User $user, string $csv)
    {
        return $this->actingAs($user)->post('/nutrition/health-import', [
            'file' => UploadedFile::fake()->createWithContent('health.csv', $csv),
        ]);
    }

    public function test_a_generic_csv_imports_steps_and_sleep_hours(): void
    {
        $user = User::factory()->create();

        $csv = implode("\n", [
            'Date,Steps,Sleep Hours',
            '2026-07-20,9500,7.5',
            '2026-07-21,12000,6',
        ]);

        $this->upload($user, $csv)->assertRedirect(route('nutrition'));

        $day = $user->intakeLogs()->whereDate('date', '2026-07-20')->firstOrFail();
        $this->assertSame(9500, $day->steps);
        $this->assertSame(450, $day->sleep_minutes);
    }

    public function test_hourly_rows_are_summed_per_day(): void
    {
        $user = User::factory()->create();

        // Health Auto Export-style header, more than one row per day.
        $csv = implode("\n", [
            'Date,Step Count (count)',
            '2026-07-20 08:00,500',
            '2026-07-20 09:00,700',
            '2026-07-20 18:00,4300',
        ]);

        $this->upload($user, $csv)->assertRedirect(route('nutrition'));

        $this->assertSame(5500, $user->intakeLogs()->whereDate('date', '2026-07-20')->firstOrFail()->steps);
    }

    public function test_a_minutes_header_is_read_as_minutes(): void
    {
        $user = User::factory()->create();

        $csv = "date,minutes asleep\n2026-07-20,430";

        $this->upload($user, $csv)->assertRedirect(route('nutrition'));

        $this->assertSame(430, $user->intakeLogs()->firstOrFail()->sleep_minutes);
    }

    public function test_the_import_never_touches_nutrition_or_weight(): void
    {
        $user = User::factory()->create();
        $user->intakeLogs()->create(['date' => '2026-07-20', 'calories' => 2500, 'weight_kg' => 81.0]);

        $this->upload($user, "Date,Steps\n2026-07-20,8000")->assertRedirect(route('nutrition'));

        $log = $user->intakeLogs()->whereDate('date', '2026-07-20')->firstOrFail();
        $this->assertSame(2500, (int) $log->calories);
        $this->assertEqualsWithDelta(81.0, (float) $log->weight_kg, 0.01);
        $this->assertSame(8000, $log->steps);
    }

    public function test_inhuman_totals_are_skipped_and_reported(): void
    {
        $user = User::factory()->create();

        $csv = implode("\n", [
            'Date,Steps',
            '2026-07-20,999999',
            '2026-07-21,9000',
        ]);

        $this->upload($user, $csv)->assertRedirect(route('nutrition'));

        $this->assertSame(1, $user->intakeLogs()->count());
        $this->assertNull($user->intakeLogs()->whereDate('date', '2026-07-20')->first());
    }

    public function test_a_file_without_steps_or_sleep_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->upload($user, "Date,Calories\n2026-07-20,2500")
            ->assertRedirect()
            ->assertSessionHas('error', __('app.health.errors.not_health'));

        $this->assertSame(0, $user->intakeLogs()->count());
    }

    public function test_the_page_shows_averages_and_flags_an_activity_mismatch(): void
    {
        $user = User::factory()->create(['activity_level' => 1.725]);

        foreach (range(1, 6) as $i) {
            $user->intakeLogs()->create([
                'date' => now()->subDays($i)->toDateString(),
                'steps' => 3000,
                'sleep_minutes' => 420,
            ]);
        }

        $this->actingAs($user)->get('/nutrition')
            ->assertOk()
            ->assertSee(number_format(3000))
            ->assertSee('7h00')
            ->assertSee(__('app.health.sedentary_but_set_high'));
    }
}
