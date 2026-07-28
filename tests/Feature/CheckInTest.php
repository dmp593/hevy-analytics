<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The check-in flow: one date, up to four poses, and the manual measurement
 * entry that is the only body-data door for accounts without an API key.
 */
class CheckInTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_check_in_saves_all_four_poses_sharing_date_weight_and_notes(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $this->actingAs($user)->post('/photos', [
            'date' => '2026-07-20',
            'photos' => [
                'front' => UploadedFile::fake()->image('f.jpg'),
                'back' => UploadedFile::fake()->image('b.jpg'),
                'left' => UploadedFile::fake()->image('l.jpg'),
                'right' => UploadedFile::fake()->image('r.jpg'),
            ],
            'weight' => 82.5,
            'notes' => 'Morning, fasted',
        ])->assertRedirect(route('photos'));

        $photos = $user->progressPhotos()->get();

        $this->assertCount(4, $photos);
        $this->assertEqualsCanonicalizing(['front', 'back', 'left', 'right'], $photos->pluck('angle')->all());
        $this->assertSame([82.5], $photos->pluck('weight_kg')->unique()->all());
        $this->assertSame(['Morning, fasted'], $photos->pluck('notes')->unique()->all());
    }

    public function test_a_check_in_with_no_photo_at_all_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/photos', [
            'date' => '2026-07-20',
            'photos' => ['front' => ''],
            'weight' => 82.5,
        ])->assertSessionHas('error');

        $this->assertSame(0, $user->progressPhotos()->count());
    }

    public function test_manual_measurements_store_metric_and_derive_lean_mass(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/body/measurements', [
            'date' => now()->toDateString(),
            'weight' => 80,
            'fat_percent' => 20,
            'waist' => 85,
            'left_bicep' => 38.5,
        ])->assertRedirect(route('body'));

        $m = $user->bodyMeasurements()->firstOrFail();

        $this->assertEqualsWithDelta(80.0, $m->weight_kg, 0.01);
        $this->assertEqualsWithDelta(64.0, $m->lean_mass_kg, 0.01);
        $this->assertEqualsWithDelta(85.0, (float) $m->waist, 0.01);
        $this->assertEqualsWithDelta(38.5, (float) $m->left_bicep_cm, 0.01);
    }

    public function test_imperial_measurements_are_converted_on_the_way_in(): void
    {
        $user = User::factory()->create(['unit_system' => 'imperial']);

        $this->actingAs($user)->post('/body/measurements', [
            'date' => now()->toDateString(),
            'weight' => 180,   // lb
            'waist' => 32,     // in
        ]);

        $m = $user->bodyMeasurements()->firstOrFail();

        $this->assertEqualsWithDelta(81.65, $m->weight_kg, 0.01);
        $this->assertEqualsWithDelta(81.3, (float) $m->waist, 0.05);
    }

    /**
     * Logging one tape number must not blank the morning's synced weight:
     * only the fields actually typed are touched.
     */
    public function test_logging_one_field_leaves_the_rest_of_the_day_alone(): void
    {
        $user = User::factory()->create();
        $user->bodyMeasurements()->create([
            'date' => now()->toDateString(),
            'weight_kg' => 80,
            'fat_percent' => 20,
        ]);

        $this->actingAs($user)->post('/body/measurements', [
            'date' => now()->toDateString(),
            'waist' => 84,
        ]);

        $this->assertSame(1, $user->bodyMeasurements()->count());

        $m = $user->bodyMeasurements()->firstOrFail();

        $this->assertEqualsWithDelta(80.0, $m->weight_kg, 0.01);
        $this->assertEqualsWithDelta(20.0, $m->fat_percent, 0.01);
        $this->assertEqualsWithDelta(84.0, (float) $m->waist, 0.01);
    }

    /** The measurement date is the day it was taken, not the day it was typed. */
    public function test_measurements_can_be_back_dated_but_not_future_dated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/body/measurements', [
            'date' => now()->subDays(3)->toDateString(),
            'weight' => 79,
        ]);

        $this->assertSame(
            now()->subDays(3)->toDateString(),
            $user->bodyMeasurements()->firstOrFail()->date->toDateString(),
        );

        $this->actingAs($user)->post('/body/measurements', [
            'date' => now()->addDay()->toDateString(),
            'weight' => 79,
        ])->assertSessionHasErrors('date');
    }

    public function test_the_gallery_orders_a_date_by_pose(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        // Uploaded out of order on purpose.
        foreach (['right', 'front', 'back'] as $pose) {
            $user->progressPhotos()->create([
                'date' => '2026-07-20',
                'angle' => $pose,
                'path' => "progress-photos/{$user->id}/{$pose}.jpg",
            ]);
        }

        $response = $this->actingAs($user)->get('/photos')->assertOk();

        // The controller's grouping is what the gallery and the comparison
        // both render from — assert on it directly.
        $order = $response->viewData('byDate')['2026-07-20']->pluck('angle')->all();

        $this->assertSame(['front', 'back', 'right'], $order);
    }
}
