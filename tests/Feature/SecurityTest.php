<?php

namespace Tests\Feature;

use App\Http\Controllers\ProgressPhotoController;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_cannot_view_another_users_routine(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $routine = $owner->routines()->create(['hevy_id' => 'r1', 'title' => 'Push']);

        $this->actingAs($intruder)->get("/routines/{$routine->hevy_id}")->assertForbidden();
        $this->actingAs($intruder)->get("/routines/{$routine->hevy_id}/edit")->assertForbidden();
    }

    public function test_users_cannot_confirm_another_users_write_operation(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $op = $owner->writeOperations()->create([
            'operation' => 'routine.update', 'method' => 'PUT', 'endpoint' => '/v1/routines/x', 'status' => 'pending',
        ]);

        $this->actingAs($intruder)->post("/write-operations/{$op->id}/confirm")->assertForbidden();
    }

    public function test_users_cannot_access_another_users_photo(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $photo = $owner->progressPhotos()->create([
            'date' => now()->toDateString(), 'angle' => 'front', 'path' => 'progress-photos/1/x.jpg',
        ]);

        $this->actingAs($intruder)->get("/photos/{$photo->id}/file")->assertForbidden();
    }

    public function test_user_id_is_not_mass_assignable(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        // Attempting to smuggle another user's id through mass assignment now
        // throws rather than being silently dropped. Both are safe, but a loud
        // failure means an attempt like this shows up in the logs instead of
        // looking like a successful write.
        $this->expectException(MassAssignmentException::class);

        $a->goals()->create(['type' => 'cut', 'user_id' => $b->id]);
    }

    public function test_a_goal_created_through_the_relationship_belongs_to_its_owner(): void
    {
        $a = User::factory()->create();

        $goal = $a->goals()->create(['type' => 'cut']);

        $this->assertSame($a->id, $goal->fresh()->user_id);
    }

    public function test_deleting_an_account_removes_progress_photo_files(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $path = 'progress-photos/'.$user->id.'/front.jpg';
        Storage::disk('local')->put($path, 'not-really-a-jpeg');

        $user->progressPhotos()->create([
            'date' => now()->toDateString(), 'angle' => 'front', 'path' => $path,
        ]);

        $this->actingAs($user)
            ->delete('/profile', ['password' => 'password'])
            ->assertRedirect('/');

        // Body photographs are special-category data under GDPR; a cascade that
        // drops the row and keeps the image is still holding the data.
        Storage::disk('local')->assertMissing($path);
    }

    public function test_progress_photos_are_capped_per_account(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $reflection = new \ReflectionClassConstant(ProgressPhotoController::class, 'MAX_PHOTOS_PER_USER');
        $limit = $reflection->getValue();

        for ($i = 0; $i < $limit; $i++) {
            $user->progressPhotos()->create([
                'date' => now()->toDateString(), 'angle' => 'front', 'path' => "p/{$i}.jpg",
            ]);
        }

        $this->actingAs($user)->post('/photos', [
            'date' => now()->toDateString(),
            'photos' => ['front' => UploadedFile::fake()->image('one-too-many.jpg')],
        ])->assertSessionHas('error');

        $this->assertSame($limit, $user->progressPhotos()->count());
    }

    public function test_responses_carry_security_headers(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }
}
