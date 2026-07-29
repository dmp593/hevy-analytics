<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\User;
use App\Support\AnalyticsCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The cross-request payload cache: exact invalidation through version
 * bumps at the write paths, and a GET that never writes.
 */
class AnalyticsCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_remember_computes_once_until_a_write_bumps_the_version(): void
    {
        $user = User::factory()->create();
        $calls = 0;
        $compute = function () use (&$calls) {
            $calls++;

            return ['n' => $calls];
        };

        AnalyticsCache::remember($user, 'probe', $compute);
        AnalyticsCache::remember($user, 'probe', $compute);
        $this->assertSame(1, $calls);

        AnalyticsCache::bump($user);
        AnalyticsCache::remember($user, 'probe', $compute);
        $this->assertSame(2, $calls);
    }

    public function test_logging_intake_bumps_the_version(): void
    {
        $user = User::factory()->create();
        $before = AnalyticsCache::version($user);

        $this->actingAs($user)->post('/nutrition/intake', [
            'date' => now()->toDateString(),
            'calories' => 2500,
        ])->assertRedirect();

        $this->assertGreaterThan($before, AnalyticsCache::version($user));
    }

    public function test_viewing_nutrition_writes_no_target_row(): void
    {
        $user = User::factory()->create([
            'height_cm' => 178, 'age' => 30, 'sex' => 'male', 'activity_level' => 1.55,
        ]);
        Goal::factory()->for($user)->create(['type' => 'lean_bulk']);
        $user->bodyMeasurements()->create(['date' => now()->toDateString(), 'weight_kg' => 80]);

        $this->actingAs($user)->get('/nutrition')->assertOk();
        $this->assertSame(0, $user->nutritionTargets()->count());

        // The POST is where persistence belongs — and it must actually write.
        $this->actingAs($user)->post('/nutrition/recompute')->assertRedirect();
        $this->assertSame(1, $user->nutritionTargets()->count());
    }
}
