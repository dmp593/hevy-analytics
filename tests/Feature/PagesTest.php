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

    /**
     * A page needs exactly one <h1>: it is what a screen reader announces as
     * "where am I". Every page in this app opened at <h2> instead, because the
     * layout shell was copy-pasted sixteen times from a starter template that
     * put the page title in the header slot as an <h2>.
     */
    #[DataProvider('pageProvider')]
    public function test_page_has_exactly_one_top_level_heading(string $path): void
    {
        // The AJAX fragments render a partial, not a page, so they have no h1.
        if (str_ends_with($path, '/data')) {
            $this->markTestSkipped('partial, not a full page');
        }

        $html = $this->actingAs($this->user)->get($path)->assertOk()->getContent();

        $this->assertSame(1, preg_match_all('/<h1[\s>]/i', $html), "{$path} must have exactly one <h1>.");
    }

    /**
     * Heading levels may not skip: an <h4> directly under an <h2> leaves a
     * screen-reader user guessing whether they missed a section.
     */
    #[DataProvider('pageProvider')]
    public function test_page_does_not_skip_heading_levels(string $path): void
    {
        if (str_ends_with($path, '/data')) {
            $this->markTestSkipped('partial, not a full page');
        }

        $html = $this->actingAs($this->user)->get($path)->assertOk()->getContent();

        preg_match_all('/<h([1-6])[\s>]/i', $html, $m);
        $levels = array_map('intval', $m[1]);

        $previous = 0;
        foreach ($levels as $level) {
            $this->assertLessThanOrEqual(
                $previous + 1,
                $level,
                "{$path} jumps from <h{$previous}> to <h{$level}>, skipping a level.",
            );
            $previous = $level;
        }
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
