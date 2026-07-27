<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The shared demo account: one click in, everything visible, nothing mutable.
 *
 * The read-only rule is the whole feature. A demo that one visitor can edit is
 * a demo the next visitor finds vandalised — so the interesting tests are the
 * ones that try to change things.
 */
class DemoModeTest extends TestCase
{
    use RefreshDatabase;

    private function demo(): User
    {
        return User::factory()->create([
            'is_demo' => true,
            'email' => 'demo@example.test',
            'sex' => 'male', 'age' => 32, 'height_cm' => 178.0,
            'hevy_api_key' => 'demo-key-not-a-real-credential',
            'hevy_last_synced_at' => now(),
        ]);
    }

    public function test_a_visitor_enters_the_demo_with_one_click(): void
    {
        $this->demo();

        $this->post('/demo')->assertRedirect(route('dashboard'));

        $this->assertTrue(auth()->user()->is_demo);
    }

    public function test_without_a_seeded_demo_the_button_fails_politely(): void
    {
        $this->post('/demo')
            ->assertRedirect(route('home'))
            ->assertSessionHas('error');

        $this->assertGuest();
    }

    public function test_the_demo_cannot_change_anything(): void
    {
        $demo = $this->demo();

        // A representative sample of every kind of mutation in the app, each
        // with its real verb — POSTing to a PATCH route would 405 before the
        // middleware and pass this test without testing anything.
        $attempts = [
            ['post', '/sync', []],
            ['post', '/goals', ['type' => 'cut']],
            ['post', '/nutrition/intake', ['date' => now()->toDateString(), 'calories' => 2500]],
            ['patch', '/profile', ['name' => 'Vandal', 'email' => 'vandal@example.test']],
            ['delete', '/profile', ['password' => 'password']],
        ];

        foreach ($attempts as [$verb, $uri, $data]) {
            $this->flushSession();

            $this->actingAs($demo)->{$verb}($uri, $data)->assertSessionHas('error');
        }

        $fresh = $demo->fresh();

        $this->assertNotSame('Vandal', $fresh->name);
        $this->assertSame(0, $fresh->goals()->count());
        $this->assertSame(0, $fresh->intakeLogs()->count());
        $this->assertSame(0, $fresh->syncLogs()->count());
    }

    public function test_the_demo_can_still_read_every_page(): void
    {
        $demo = $this->demo();

        foreach (['/dashboard', '/muscle', '/body', '/projections', '/ai', '/billing'] as $page) {
            $this->actingAs($demo)->get($page)->assertOk();
        }
    }

    public function test_the_demo_can_leave_for_the_register_page(): void
    {
        $demo = $this->demo();

        $this->actingAs($demo)->post('/demo/leave')->assertRedirect(route('register'));

        $this->assertGuest();
    }

    public function test_the_demo_can_switch_language_and_log_out(): void
    {
        $demo = $this->demo();

        $this->actingAs($demo)->post('/locale/pt')->assertSessionMissing('error');
        $this->actingAs($demo)->post('/logout')->assertSessionMissing('error');

        $this->assertGuest();
    }

    /** Being signed in as demo must not block a real person's own mutations. */
    public function test_a_real_account_is_untouched_by_the_middleware(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/goals', ['type' => 'cut'])
            ->assertSessionMissing('error');

        $this->assertSame(1, $user->goals()->count());
    }

    public function test_a_signed_in_user_cannot_be_swapped_into_the_demo(): void
    {
        $this->demo();
        $user = User::factory()->create();

        // guest middleware bounces them to their own dashboard instead.
        $this->actingAs($user)->post('/demo')->assertRedirect(route('dashboard'));

        $this->assertSame($user->id, auth()->id());
    }

    public function test_the_landing_page_offers_the_demo(): void
    {
        $this->get('/')->assertSee(route('demo.enter'));
    }
}
