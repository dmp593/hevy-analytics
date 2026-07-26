<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        // Attempt to smuggle another user's id through mass assignment.
        $goal = $a->goals()->create(['type' => 'cut', 'user_id' => $b->id]);

        $this->assertSame($a->id, $goal->fresh()->user_id);
    }

    public function test_responses_carry_security_headers(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }
}
