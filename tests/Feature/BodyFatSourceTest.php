<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Analytics\BodyCompAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BodyFatSourceTest extends TestCase
{
    use RefreshDatabase;

    private function seedMeasurements(User $user): void
    {
        // 10 weekly readings, weight rising, scale fat% noisy but flat-ish.
        for ($i = 0; $i < 10; $i++) {
            $user->bodyMeasurements()->create([
                'date' => Carbon::now()->subDays(63 - $i * 7)->toDateString(),
                'weight_kg' => 70 + $i * 0.2,
                'fat_percent' => 18 + (($i % 2) ? 0.5 : -0.5), // noisy
                'neck_cm' => 38,
                'abdomen' => 84,
                'waist' => 82,
            ]);
        }
    }

    public function test_source_switch_changes_effective_fat_percent(): void
    {
        $user = User::factory()->create(['height_cm' => 178, 'sex' => 'male', 'body_fat_source' => 'scale']);
        $this->seedMeasurements($user);

        $scaleStatus = (new BodyCompAnalytics($user))->status();
        $this->assertSame('scale', $scaleStatus['fat_source']);

        $user->update(['body_fat_source' => 'navy']);
        $navyStatus = (new BodyCompAnalytics($user->fresh()))->status();
        $this->assertSame('navy', $navyStatus['fat_source']);
        $this->assertNotNull($navyStatus['navy_fat_percent']);
        // Navy result should drive the effective fat% under navy source.
        $this->assertEqualsWithDelta($navyStatus['navy_fat_percent'], $navyStatus['fat_percent'], 0.01);
    }

    public function test_partitioning_reports_confidence_and_source(): void
    {
        $user = User::factory()->create(['height_cm' => 178, 'sex' => 'male', 'body_fat_source' => 'scale']);
        $this->seedMeasurements($user);

        $part = (new BodyCompAnalytics($user))->partitioning();

        $this->assertArrayHasKey('reliable', $part);
        $this->assertArrayHasKey('fat_points', $part);
        $this->assertSame('scale', $part['source']);
        $this->assertSame(10, $part['fat_points']);
    }

    public function test_manual_source_uses_logged_fat_percent(): void
    {
        $user = User::factory()->create(['height_cm' => 178, 'sex' => 'male', 'body_fat_source' => 'manual']);
        $this->seedMeasurements($user);
        $user->intakeLogs()->create(['date' => Carbon::now()->toDateString(), 'fat_percent' => 12.0]);

        $status = (new BodyCompAnalytics($user))->status();
        $this->assertEqualsWithDelta(12.0, $status['fat_percent'], 0.01);
    }
}
