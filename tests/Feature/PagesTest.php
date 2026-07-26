<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PagesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'hevy_api_key' => '00000000-0000-0000-0000-000000000000',
            'sex' => 'male',
            'age' => 30,
            'height_cm' => 178,
            'activity_level' => 1.55,
        ]);

        $this->user->goals()->create(['type' => 'lean_bulk', 'is_active' => true, 'started_at' => now()]);
    }

    public static function pageProvider(): array
    {
        return [
            'dashboard' => ['/dashboard'],
            'performance' => ['/performance'],
            'performance data' => ['/performance/data'],
            'strength levels' => ['/strength-levels'],
            'muscle' => ['/muscle'],
            'muscle data' => ['/muscle/data'],
            'body' => ['/body'],
            'photos' => ['/photos'],
            'guide' => ['/guide'],
            'nutrition' => ['/nutrition'],
            'projections' => ['/projections'],
            'routines' => ['/routines'],
            'goals' => ['/goals'],
            'ai' => ['/ai'],
            'write index' => ['/write-operations'],
            'profile' => ['/profile'],
        ];
    }

    /** @dataProvider pageProvider */
    #[DataProvider('pageProvider')]
    public function test_page_renders(string $path): void
    {
        $this->actingAs($this->user)->get($path)->assertOk();
    }

    public function test_goal_can_be_stored(): void
    {
        $this->actingAs($this->user)->post('/goals', ['type' => 'cut'])->assertRedirect();

        $this->assertSame('cut', $this->user->fresh()->activeGoal()->type);
    }

    public function test_intake_can_be_logged(): void
    {
        $this->actingAs($this->user)->post('/nutrition/intake', [
            'date' => now()->toDateString(),
            'calories' => 2500,
            'protein_g' => 160,
        ])->assertRedirect();

        $this->assertSame(1, $this->user->intakeLogs()->count());
    }
}
