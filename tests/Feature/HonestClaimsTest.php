<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Guards on things the app states to the athlete.
 *
 * Each of these pins a claim the app made that its own code contradicted, or a
 * side effect that quietly cost the user something. They are grouped because
 * they share a cause: a sentence written once, next to code that later moved.
 */
class HonestClaimsTest extends TestCase
{
    use RefreshDatabase;

    private function athlete(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'hevy_api_key' => '00000000-0000-0000-0000-000000000000',
            'sex' => 'male',
            'age' => 30,
            'height_cm' => 178,
            'activity_level' => 1.55,
        ], $attributes));
    }

    /**
     * The strength page needs A sex to compute against and falls back to male.
     * It used to then print "compared against male lifters" as plain fact, so a
     * woman who had not filled in her profile was shown percentiles computed
     * against men and told nothing.
     */
    public function test_strength_levels_says_when_it_has_assumed_your_sex(): void
    {
        $user = $this->athlete(['sex' => null]);
        $user->bodyMeasurements()->create(['date' => now(), 'weight_kg' => 80]);

        $this->actingAs($user)->get('/strength-levels')
            ->assertOk()
            ->assertSee(__('app.levels.assumed_sex'));
    }

    public function test_strength_levels_does_not_warn_when_sex_is_known(): void
    {
        $user = $this->athlete(['sex' => 'female']);
        $user->bodyMeasurements()->create(['date' => now(), 'weight_kg' => 65]);

        $this->actingAs($user)->get('/strength-levels')
            ->assertOk()
            ->assertDontSee(__('app.levels.assumed_sex'));
    }

    /**
     * The projections page claimed "logarithmic dampening near natural
     * ceilings". ProjectionService applies 1/(1+log(1+days/30)) — a decay in
     * time alone. No ceiling, FFMI or bodyweight enters it.
     */
    public function test_projections_does_not_claim_a_ceiling_model_it_does_not_have(): void
    {
        $response = $this->actingAs($this->athlete())->get('/projections')->assertOk();

        $response->assertDontSee('natural ceilings');
        $response->assertDontSee('logarithmic dampening');
    }

    /**
     * Every horizon delta was green when positive. On body fat and waist, that
     * told the athlete the opposite of the truth.
     */
    public function test_a_rising_waist_projection_is_not_coloured_as_good(): void
    {
        $user = $this->athlete();

        // A clean upward waist trend, enough points for the fit to be reported.
        foreach (range(0, 11) as $i) {
            $user->bodyMeasurements()->create([
                'date' => now()->subWeeks(11 - $i),
                'weight_kg' => 80,
                'waist' => 80 + $i * 0.5,
            ]);
        }

        $html = $this->actingAs($user)->get('/projections')->assertOk()->getContent();

        // Isolate the waist card and confirm its positive deltas are not good-toned.
        $start = strpos($html, __('app.projections.waist'));
        $this->assertNotFalse($start, 'waist projection card not rendered');
        $card = substr($html, $start, 2000);

        $this->assertStringContainsString('text-bad', $card);
        $this->assertStringNotContainsString('text-good">+', $card);
    }

    /**
     * The generate button submitted force=1, which bypasses the content-hash
     * cache and bills a provider call on every press against a monthly
     * allowance — and made the "nothing has changed" path unreachable.
     */
    public function test_the_ai_page_does_not_force_a_billed_call_on_every_press(): void
    {
        $html = $this->actingAs($this->athlete())->get('/ai')->assertOk()->getContent();

        $this->assertStringNotContainsString('name="force"', $html);
    }

    /**
     * The four fine-tune inputs carried a placeholder and no value, and store()
     * writes a new row from whatever was submitted. Re-saving therefore blanked
     * an override the athlete had deliberately set.
     */
    public function test_goal_fine_tuning_survives_a_round_trip_through_the_form(): void
    {
        $user = $this->athlete();

        $this->actingAs($user)->post('/goals', [
            'type' => 'lean_bulk',
            'protein_g_per_kg' => 2.4,
        ])->assertRedirect();

        $stored = $user->fresh()->activeGoal()->protein_g_per_kg;
        $this->assertEqualsWithDelta(2.4, (float) $stored, 0.001);

        // The stored override must come back in the field, not just as a hint:
        // a placeholder looks identical and submits nothing.
        $this->actingAs($user)->get('/goals')
            ->assertOk()
            ->assertSee('value="'.$stored.'"', escape: false);
    }

    public function test_the_goal_picker_does_not_claim_to_change_training_landmarks(): void
    {
        // MuscleLandmarks::classify() takes no goal argument and its constants
        // never move, so a goal cannot adjust them.
        $this->actingAs($this->athlete())->get('/goals')
            ->assertOk()
            ->assertDontSee('training landmarks');
    }

    /**
     * A tone is a judgement. Applied to a missing value it becomes a judgement
     * about nothing — the waist/height tile painted an athlete who had never
     * measured their waist a reassuring green.
     */
    public function test_a_stat_tile_with_no_value_is_never_toned(): void
    {
        $rendered = $this->blade(
            '<x-ui.stat label="Waist/height" :value="null" tone="good" />',
        );

        $this->assertStringNotContainsString('text-good', $rendered);
        $this->assertStringContainsString('—', $rendered);
    }

    /**
     * /body honours the athlete's chosen body-fat source; /projections used the
     * raw scale column, so a Navy-tape user saw two different body-fat numbers
     * for the same day on two pages.
     */
    public function test_body_fat_projection_uses_the_same_source_as_the_body_page(): void
    {
        $user = $this->athlete(['body_fat_source' => 'navy']);

        foreach (range(0, 11) as $i) {
            $user->bodyMeasurements()->create([
                'date' => now()->subWeeks(11 - $i),
                'weight_kg' => 80,
                'fat_percent' => 30,       // the scale column, deliberately absurd
                'neck_cm' => 38,
                'abdomen' => 85,
                'waist' => 80,
            ]);
        }

        $html = $this->actingAs($user)->get('/projections')->assertOk()->getContent();

        $start = strpos($html, __('app.projections.fat_percent'));
        $this->assertNotFalse($start);
        $card = substr($html, $start, 800);

        // Navy from these measurements is far below the 30% scale reading; if
        // the raw column leaked through, the current value would show as 30.
        $this->assertStringNotContainsString('>30<', $card);
    }

    /**
     * The landing page says nothing is written to Hevy unless you confirm it.
     *
     * Staging is the step people click from a routine page, so it is the step
     * where an accidental write would happen — and an app that edits someone's
     * training programme without being asked is a very different product from
     * the one being sold.
     */
    public function test_staging_a_progression_does_not_write_anything_to_hevy(): void
    {
        Http::preventStrayRequests();

        $user = $this->athlete();
        $routine = $user->routines()->create(['hevy_id' => 'r1', 'title' => 'Push']);
        $routine->exercises()->create([
            'index' => 0,
            'title' => 'Bench Press (Barbell)',
            'exercise_template_hevy_id' => 'BENCH',
            'sets' => [['index' => 0, 'type' => 'normal', 'weight_kg' => 60, 'reps' => 8]],
        ]);

        $this->actingAs($user)->post("/routines/{$routine->hevy_id}/stage-progression")->assertRedirect();

        // Staged and waiting, not sent. preventStrayRequests() would have thrown
        // on any outbound call, so reaching here is itself half the assertion.
        $this->assertSame('pending', $user->writeOperations()->sole()->status);
    }

    /**
     * The landing page states the free history cap as the reason to subscribe.
     * It is also the only thing behind the paywall, so if it were not actually
     * enforced the pitch would be selling something already given away.
     */
    public function test_the_free_history_cap_is_really_enforced(): void
    {
        $user = User::factory()->free()->create();
        $days = (int) config('billing.entitlements.free.history_days');

        $this->assertSame($days, $user->entitlements()->historyDays());

        // A date older than the cap is pulled forward to it; one inside is left.
        $this->assertTrue($user->entitlements()->clampFrom(now()->subDays($days * 3))
            ->greaterThanOrEqualTo(now()->subDays($days + 1)));
        $this->assertNull(User::factory()->create(['trial_ends_at' => now()->addDay()])->entitlements()->historyDays());
    }

    public function test_goal_history_is_preserved_when_a_new_goal_is_set(): void
    {
        $user = $this->athlete();

        $this->actingAs($user)->post('/goals', ['type' => 'lean_bulk'])->assertRedirect();
        $this->actingAs($user)->post('/goals', ['type' => 'cut'])->assertRedirect();

        $this->assertSame(2, $user->goals()->count());
        $this->assertSame(1, $user->goals()->where('is_active', true)->count());
        $this->assertSame('cut', $user->fresh()->activeGoal()->type);
        $this->assertInstanceOf(Goal::class, $user->activeGoal());
    }
}
