<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Daily nutrition totals from diet-app CSVs: per-meal and per-food rows are
 * summed per date, re-uploads merge, and a nutrition import never touches
 * weights logged that day.
 */
class NutritionCsvImportTest extends TestCase
{
    use RefreshDatabase;

    private function upload(User $user, string $csv)
    {
        return $this->actingAs($user)->post('/nutrition/import', [
            'file' => UploadedFile::fake()->createWithContent('diet.csv', $csv),
        ]);
    }

    public function test_myfitnesspal_meal_rows_sum_into_daily_totals(): void
    {
        $user = User::factory()->create();

        $csv = implode("\n", [
            'Date,Meal,Calories,Fat (g),Carbohydrates (g),Protein (g)',
            '2026-07-20,Breakfast,600,20,70,30',
            '2026-07-20,Lunch,900,30,100,45',
            '2026-07-20,Dinner,800,25,80,50',
            '2026-07-21,Breakfast,550,18,60,35',
        ]);

        $this->upload($user, $csv)
            ->assertRedirect(route('nutrition'))
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'MyFitnessPal'));

        $this->assertSame(2, $user->intakeLogs()->count());

        $day = $user->intakeLogs()->whereDate('date', '2026-07-20')->firstOrFail();
        $this->assertSame(2300, (int) $day->calories);
        $this->assertSame(125, (int) $day->protein_g);
        $this->assertSame(75, (int) $day->fat_g);
        $this->assertSame(250, (int) $day->carb_g);
    }

    public function test_cronometer_daily_rows_import_as_they_are(): void
    {
        $user = User::factory()->create();

        $csv = implode("\n", [
            'Date,Energy (kcal),Protein (g),Carbs (g),Fat (g),Fiber (g)',
            '2026-07-19,2450.7,180.2,240.9,80.1,38',
        ]);

        $this->upload($user, $csv)
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'Cronometer'));

        $day = $user->intakeLogs()->whereDate('date', '2026-07-19')->firstOrFail();
        $this->assertSame(2451, (int) $day->calories);
        $this->assertSame(180, (int) $day->protein_g);
    }

    public function test_loseit_food_rows_sum_and_deleted_rows_are_excluded(): void
    {
        $user = User::factory()->create();

        $csv = implode("\n", [
            'Date,Name,Type,Quantity,Units,Calories,Deleted,Fat (g),Protein (g),Carbohydrates (g)',
            '07/18/2026,Oats,Breakfast,1,cup,300,false,6,10,55',
            '07/18/2026,Chicken,Lunch,200,g,330,false,7,62,0',
            '07/18/2026,Mistake,Lunch,1,unit,999,true,10,10,10',
        ]);

        $this->upload($user, $csv)
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'Lose It'));

        $day = $user->intakeLogs()->whereDate('date', '2026-07-18')->firstOrFail();
        $this->assertSame(630, (int) $day->calories);
        $this->assertSame(72, (int) $day->protein_g);
    }

    public function test_reimporting_corrects_instead_of_duplicating(): void
    {
        $user = User::factory()->create();

        $csv = fn ($cal) => "Date,Meal,Calories\n2026-07-20,Lunch,{$cal}";

        $this->upload($user, $csv(1000));
        $this->upload($user, $csv(1200));

        $this->assertSame(1, $user->intakeLogs()->count());
        $this->assertSame(1200, (int) $user->intakeLogs()->firstOrFail()->calories);
    }

    public function test_the_import_never_touches_a_logged_weight(): void
    {
        $user = User::factory()->create();
        $user->intakeLogs()->create(['date' => '2026-07-20', 'weight_kg' => 82.5]);

        $this->upload($user, "Date,Meal,Calories,Protein (g)\n2026-07-20,Lunch,900,45");

        $day = $user->intakeLogs()->whereDate('date', '2026-07-20')->firstOrFail();
        $this->assertEqualsWithDelta(82.5, (float) $day->weight_kg, 0.01);
        $this->assertSame(900, (int) $day->calories);
    }

    public function test_a_workout_export_is_refused_loudly(): void
    {
        $user = User::factory()->create();

        $this->upload($user, "title,start_time,exercise_title,weight_kg,reps\nPush,2026-07-20,Bench,100,5")
            ->assertSessionHas('error');

        $this->assertSame(0, $user->intakeLogs()->count());
    }

    public function test_an_absurd_day_is_skipped_and_reported(): void
    {
        $user = User::factory()->create();

        $csv = implode("\n", [
            'Date,Meal,Calories',
            '2026-07-20,Lunch,900',
            '2026-07-21,Glitch,999999',
        ]);

        $this->upload($user, $csv)
            ->assertSessionHas('status', fn ($s) => str_contains($s, '1'));

        $this->assertSame(1, $user->intakeLogs()->count());
    }
}
