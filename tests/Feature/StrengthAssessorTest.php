<?php

namespace Tests\Feature;

use App\Services\StrengthStandards\OpenPowerliftingStandards;
use App\Services\StrengthStandards\StrengthAssessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StrengthAssessorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_uses_fitnessvolt_when_available(): void
    {
        config()->set('services.fitnessvolt.enabled', true);
        Http::fake([
            '*/public/rank*' => Http::response([
                'success' => true,
                'fv_score' => 46,
                'tier' => 'Novice',
                'gym' => ['percentile' => 83, 'sample_size' => 11069, 'source' => 'Symmetric Strength'],
                'verified' => ['percentile' => 46, 'sample_size' => 41754, 'source' => 'OpenPowerlifting'],
                'attribution' => ['text' => 'Powered by FitnessVolt', 'url' => 'https://fitnessvolt.com/', 'license' => 'CC BY 4.0'],
            ], 200),
        ]);

        $eval = app(StrengthAssessor::class)->assess('Bench Press (Barbell)', 100, 68, 'male', 32);

        $this->assertSame('fitnessvolt', $eval['source']);
        $this->assertSame(83.0, $eval['percentile']); // headline = gym
        $this->assertSame('Advanced', $eval['level']);
        $this->assertArrayHasKey('gym', $eval['populations']);
        $this->assertArrayHasKey('verified', $eval['populations']);
    }

    public function test_falls_back_to_builtin_when_fitnessvolt_disabled(): void
    {
        config()->set('services.fitnessvolt.enabled', false);

        $eval = app(StrengthAssessor::class)->assess('Bench Press (Barbell)', 100, 68, 'male', 32);

        $this->assertSame('builtin', $eval['source']);
        $this->assertNotEmpty($eval['thresholds_kg']);
    }

    public function test_falls_back_to_opl_when_fitnessvolt_fails(): void
    {
        config()->set('services.fitnessvolt.enabled', true);
        Http::fake(['*/public/rank*' => Http::response('', 500)]);

        // Seed a minimal OPL table.
        Storage::disk('local')->put(OpenPowerliftingStandards::PATH, json_encode([
            'generated' => now()->toIso8601String(),
            'lifts' => [
                'bench' => [
                    'male' => [[
                        'bw' => 70, 'n' => 5000,
                        'breakpoints' => [
                            ['p' => 5, 'w' => 60], ['p' => 50, 'w' => 110], ['p' => 95, 'w' => 170],
                        ],
                    ]],
                ],
            ],
        ], JSON_UNESCAPED_SLASHES));

        $eval = app(StrengthAssessor::class)->assess('Bench Press (Barbell)', 110, 70, 'male', 30);

        $this->assertSame('openpowerlifting', $eval['source']);
        $this->assertEqualsWithDelta(50, $eval['percentile'], 1);
    }

    public function test_unmapped_lift_uses_builtin(): void
    {
        config()->set('services.fitnessvolt.enabled', true);
        Http::fake(); // no rank call expected for a non-FV lift

        $eval = app(StrengthAssessor::class)->assess('EZ Bar Biceps Curl', 31, 68, 'male', 32);

        $this->assertSame('builtin', $eval['source']);
    }
}
