<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Services\Analytics\NutritionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\SeedsTrainingData;
use Tests\TestCase;

/**
 * The adaptive-TDEE blend earns weight with data: at the 7-day minimum the
 * formula still dominates, at a full month of logs the measurement carries
 * 80% — and the stored TDEE must be exactly the blend the basis declares.
 */
class AdaptiveBlendTest extends TestCase
{
    use RefreshDatabase, SeedsTrainingData;

    public function test_the_adaptive_estimate_earns_weight_with_logged_days(): void
    {
        foreach ([7 => 0.35, 28 => 0.8] as $days => $expectedWeight) {
            $user = $this->makeAthlete();
            Goal::factory()->for($user)->create(['type' => 'lean_bulk']);
            $this->seedWeightTrend($user, 80.0, 0.25, 5);

            foreach (range(0, $days - 1) as $i) {
                $user->intakeLogs()->create([
                    'date' => Carbon::now()->subDays($i)->toDateString(),
                    'calories' => 3000,
                ]);
            }

            $target = (new NutritionService($user))->computeTargets();
            $basis = $target->basis;

            $this->assertSame($days, $basis['adaptive_logged_days'], "days=$days");
            $this->assertEqualsWithDelta($expectedWeight, $basis['adaptive_weight'], 0.001, "days=$days");

            $expectedTdee = round(
                $basis['formula_tdee'] * (1 - $expectedWeight)
                + $basis['adaptive_maintenance'] * $expectedWeight,
                1,
            );
            $this->assertEqualsWithDelta($expectedTdee, (float) $target->tdee, 0.11, "days=$days");
        }
    }

    public function test_six_logged_days_leave_the_formula_alone(): void
    {
        $user = $this->makeAthlete();
        Goal::factory()->for($user)->create(['type' => 'lean_bulk']);
        $this->seedWeightTrend($user, 80.0, 0.25, 5);

        foreach (range(0, 5) as $i) {
            $user->intakeLogs()->create([
                'date' => Carbon::now()->subDays($i)->toDateString(),
                'calories' => 3000,
            ]);
        }

        $target = (new NutritionService($user))->computeTargets();

        $this->assertNull($target->basis['adaptive_maintenance']);
        $this->assertNull($target->basis['adaptive_weight']);
        $this->assertEqualsWithDelta((float) $target->basis['formula_tdee'], (float) $target->tdee, 0.11);
    }
}
