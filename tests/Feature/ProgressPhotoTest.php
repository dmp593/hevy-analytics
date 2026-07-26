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
            'weight_kg' => 70,
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
}
