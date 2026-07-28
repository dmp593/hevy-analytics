<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workout;
use App\Support\Units;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Metric is the only thing the database ever holds; imperial exists purely at
 * the form and page edges. These tests pin both halves: the arithmetic, and
 * that every edge actually converts instead of storing a pounds number in a
 * kilograms column.
 */
class UnitsTest extends TestCase
{
    use RefreshDatabase;

    private function imperialUser(): User
    {
        return User::factory()->create(['unit_system' => 'imperial']);
    }

    public function test_conversions_are_correct_and_round_trip(): void
    {
        $imperial = Units::for($this->imperialUser());

        $this->assertEqualsWithDelta(220.5, $imperial->weight(100), 0.01);
        $this->assertEqualsWithDelta(81.65, $imperial->weightToKg(180), 0.01);
        $this->assertEqualsWithDelta(15.7, $imperial->girth(40), 0.05);
        $this->assertEqualsWithDelta(101.6, $imperial->girthToCm(40), 0.01);

        // A value converted out and typed back in must not drift.
        $this->assertEqualsWithDelta(100.0, $imperial->weightToKg($imperial->weight(100, 2)), 0.01);

        $metric = Units::metric();
        $this->assertSame(82.4, $metric->weight(82.4));
        $this->assertNull($metric->weight(null));
        $this->assertSame('kg', $metric->weightUnit());
        $this->assertSame('lb', $imperial->weightUnit());
    }

    public function test_height_folds_between_centimetres_and_feet_inches(): void
    {
        $this->assertSame([5, 10.0], Units::heightParts(177.8));
        $this->assertEqualsWithDelta(177.8, Units::heightToCm(5, 10), 0.01);

        // Rounding inches must carry into the next foot, never show 5 ft 12 in.
        [$ft, $in] = Units::heightParts(182.87);
        $this->assertSame(6, $ft);
        $this->assertSame(0.0, $in);
    }

    public function test_imperial_profile_saves_height_from_feet_and_inches(): void
    {
        $user = $this->imperialUser();

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'unit_system' => 'imperial',
            'height_ft' => 5,
            'height_in' => 10,
        ])->assertRedirect('/profile');

        $this->assertEqualsWithDelta(177.8, $user->fresh()->height_cm, 0.01);
    }

    public function test_metric_profile_still_saves_centimetres(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'unit_system' => 'metric',
            'height_cm' => 178,
        ])->assertRedirect('/profile');

        $this->assertSame('metric', $user->fresh()->unit_system);
        $this->assertEqualsWithDelta(178.0, $user->fresh()->height_cm, 0.01);
    }

    public function test_the_one_tap_switch_persists_and_rejects_nonsense(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/settings/units/imperial')->assertRedirect();
        $this->assertSame('imperial', $user->fresh()->unit_system);

        $this->actingAs($user)->post('/settings/units/stone')->assertNotFound();
        $this->assertSame('imperial', $user->fresh()->unit_system);
    }

    public function test_photo_weight_typed_in_pounds_lands_in_kilograms(): void
    {
        Storage::fake('local');
        $user = $this->imperialUser();

        $this->actingAs($user)->post('/photos', [
            'date' => now()->toDateString(),
            'photos' => ['front' => UploadedFile::fake()->image('p.jpg')],
            'weight' => 180,
        ]);

        $this->assertEqualsWithDelta(81.65, $user->progressPhotos()->firstOrFail()->weight_kg, 0.01);
    }

    public function test_intake_weight_typed_in_pounds_lands_in_kilograms(): void
    {
        $user = $this->imperialUser();

        $this->actingAs($user)->post('/nutrition/intake', [
            'date' => now()->toDateString(),
            'calories' => 2500,
            'weight' => 180,
        ]);

        $this->assertEqualsWithDelta(81.65, $user->intakeLogs()->firstOrFail()->weight_kg, 0.01);
    }

    public function test_dashboard_speaks_pounds_to_an_imperial_athlete(): void
    {
        $user = $this->imperialUser();
        $user->bodyMeasurements()->create(['date' => now()->toDateString(), 'weight_kg' => 100]);
        // A workout takes the account out of first-run mode, so the dashboard
        // renders the tiles instead of the welcome card.
        Workout::factory()->for($user)->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSee('220.5');
        $response->assertSee('lb');
        $response->assertDontSee('220.5 kg');
    }

    public function test_body_page_converts_tape_measurements_to_inches(): void
    {
        $user = $this->imperialUser();
        $user->bodyMeasurements()->create([
            'date' => now()->toDateString(),
            'weight_kg' => 100,
            'left_bicep_cm' => 40,
            'right_bicep_cm' => 40,
        ]);

        // 40 cm = 15.7 in, shown in the symmetry list.
        $this->actingAs($user)->get('/body')->assertSee('15.7');
    }

    public function test_welcome_card_offers_the_unit_switch_on_day_one(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')
            ->assertSee(route('settings.units', 'imperial'))
            ->assertSee(__('app.profile.unit_imperial_short'));
    }
}
