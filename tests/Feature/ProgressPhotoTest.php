<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProgressPhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_photo_can_be_uploaded_and_streamed_to_owner(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $this->actingAs($user)->post('/photos', [
            'date' => now()->toDateString(),
            'angle' => 'front',
            'photo' => UploadedFile::fake()->image('progress.jpg', 300, 400),
            'weight' => 70,
        ])->assertRedirect();

        $photo = $user->progressPhotos()->firstOrFail();
        Storage::disk('local')->assertExists($photo->path);

        $this->actingAs($user)->get("/photos/{$photo->id}/file")->assertOk();
    }

    public function test_photos_are_private_to_other_users(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $this->actingAs($owner)->post('/photos', [
            'date' => now()->toDateString(),
            'angle' => 'side',
            'photo' => UploadedFile::fake()->image('p.jpg'),
        ]);

        $photo = $owner->progressPhotos()->firstOrFail();

        $this->actingAs($intruder)->get("/photos/{$photo->id}/file")->assertForbidden();
        $this->actingAs($intruder)->delete("/photos/{$photo->id}")->assertForbidden();
    }

    public function test_owner_can_delete_photo(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $this->actingAs($user)->post('/photos', [
            'date' => now()->toDateString(),
            'angle' => 'back',
            'photo' => UploadedFile::fake()->image('p.jpg'),
        ]);

        $photo = $user->progressPhotos()->firstOrFail();
        $this->actingAs($user)->delete("/photos/{$photo->id}")->assertRedirect();

        $this->assertDatabaseMissing('progress_photos', ['id' => $photo->id]);
        Storage::disk('local')->assertMissing($photo->path);
    }

    /**
     * On a container host the local disk is wiped on every deploy, so
     * production stores these on object storage. The whole upload → stream →
     * delete cycle has to work against whatever disk is configured, and a
     * hardcoded 'local' anywhere would pass the tests above while losing every
     * photo in production.
     */
    public function test_photos_follow_the_configured_disk(): void
    {
        config(['filesystems.photos' => 'cloud-test']);
        Storage::fake('cloud-test');
        Storage::fake('local');

        $user = User::factory()->create();

        $this->actingAs($user)->post('/photos', [
            'date' => now()->toDateString(),
            'angle' => 'front',
            'photo' => UploadedFile::fake()->image('p.jpg'),
        ])->assertRedirect();

        $photo = $user->progressPhotos()->firstOrFail();

        Storage::disk('cloud-test')->assertExists($photo->path);
        // Nothing was written to the disk that does not survive a deploy.
        Storage::disk('local')->assertMissing($photo->path);

        $this->actingAs($user)->get("/photos/{$photo->id}/file")->assertOk();

        $this->actingAs($user)->delete("/photos/{$photo->id}")->assertRedirect();
        Storage::disk('cloud-test')->assertMissing($photo->path);
    }

    /**
     * Deleting an account must take the photographs with it — GDPR treats them
     * as special-category data, and the cascade is a path no controller sees.
     */
    public function test_deleting_an_account_removes_its_photos_from_cloud_storage(): void
    {
        config(['filesystems.photos' => 'cloud-test']);
        Storage::fake('cloud-test');

        $user = User::factory()->create();
        $this->actingAs($user)->post('/photos', [
            'date' => now()->toDateString(),
            'angle' => 'front',
            'photo' => UploadedFile::fake()->image('p.jpg'),
        ]);

        $path = $user->progressPhotos()->firstOrFail()->path;

        $this->actingAs($user)->delete('/profile', ['password' => 'password'])->assertRedirect();

        Storage::disk('cloud-test')->assertMissing($path);
    }
}
