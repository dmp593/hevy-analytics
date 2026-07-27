<?php

namespace Tests\Feature;

use App\Billing\State;
use App\Models\AdminAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Subscribes;
use Tests\TestCase;

/**
 * The admin area is the most sensitive surface in the app: it can read every
 * account and give away the product. These tests are mostly about who cannot
 * reach it, and about the fact that everything done through it is recorded.
 */
class AdminTest extends TestCase
{
    use RefreshDatabase, Subscribes;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    // ------------------------------------------------------------ who gets in

    /**
     * 404, not 403. A 403 confirms the route exists and that the account merely
     * lacks a flag, which is free reconnaissance for anyone probing.
     */
    public function test_a_non_admin_is_told_the_admin_area_does_not_exist(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->get('/admin/users')->assertNotFound();
        $this->actingAs($user)->get("/admin/users/{$other->id}")->assertNotFound();
        $this->actingAs($user)->post("/admin/users/{$other->id}/grant", ['days' => 30, 'reason' => 'x'])->assertNotFound();
        $this->actingAs($user)->post("/admin/users/{$other->id}/revoke")->assertNotFound();
        $this->actingAs($user)->post("/admin/users/{$other->id}/cancel-subscription", ['confirm' => $other->email])->assertNotFound();
    }

    public function test_a_guest_cannot_reach_the_admin_area(): void
    {
        $this->get('/admin/users')->assertRedirect('/login');
    }

    /** The flag is not fillable, so it cannot be set through a normal form post. */
    public function test_the_admin_flag_cannot_be_mass_assigned(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', [
            'name' => 'Still Me',
            'email' => $user->email,
            'is_admin' => true,
        ]);

        $this->assertFalse($user->fresh()->is_admin);
    }

    public function test_an_admin_can_list_and_open_accounts(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['name' => 'Findable Athlete']);

        $this->actingAs($admin)->get('/admin/users')
            ->assertOk()
            ->assertSee('Findable Athlete');

        $this->actingAs($admin)->get("/admin/users/{$target->id}")
            ->assertOk()
            ->assertSee($target->email);
    }

    public function test_the_list_can_be_searched(): void
    {
        $admin = $this->admin();
        User::factory()->create(['name' => 'Alice Athlete', 'email' => 'alice@example.test']);
        User::factory()->create(['name' => 'Bob Builder', 'email' => 'bob@example.test']);

        $this->actingAs($admin)->get('/admin/users?q=alice')
            ->assertOk()
            ->assertSee('Alice Athlete')
            ->assertDontSee('Bob Builder');
    }

    // ------------------------------------------------------------------ comps

    public function test_granting_access_changes_the_entitlement_and_is_recorded(): void
    {
        $admin = $this->admin();
        $target = User::factory()->free()->create();

        $this->assertSame(State::Free, $target->billingState());

        $this->actingAs($admin)
            ->post("/admin/users/{$target->id}/grant", ['days' => 60, 'reason' => 'Refund for a bad month'])
            ->assertRedirect();

        $target->refresh();

        $this->assertSame(State::Complimentary, $target->billingState());
        $this->assertNull($target->entitlements()->historyDays());
        $this->assertSame(60, (int) round(now()->diffInDays($target->comped_until, false)));

        $action = AdminAction::where('target_user_id', $target->id)->latest()->firstOrFail();

        $this->assertSame(AdminAction::GRANTED_ACCESS, $action->action);
        $this->assertSame($admin->id, $action->admin_id);
        $this->assertStringContainsString('Refund for a bad month', $action->detail);
    }

    /** An unbounded grant is one nobody ever revisits. */
    public function test_a_grant_must_have_a_length_and_a_reason(): void
    {
        $admin = $this->admin();
        $target = User::factory()->free()->create();

        $this->actingAs($admin)->post("/admin/users/{$target->id}/grant", ['days' => 30])
            ->assertSessionHasErrors('reason');

        $this->actingAs($admin)->post("/admin/users/{$target->id}/grant", ['reason' => 'x'])
            ->assertSessionHasErrors('days');

        $this->actingAs($admin)->post("/admin/users/{$target->id}/grant", ['days' => 99999, 'reason' => 'x'])
            ->assertSessionHasErrors('days');

        $this->assertNull($target->fresh()->comped_until);
    }

    public function test_revoking_access_returns_the_athlete_to_their_real_state(): void
    {
        $admin = $this->admin();
        $target = User::factory()->free()->create();

        $this->actingAs($admin)->post("/admin/users/{$target->id}/grant", ['days' => 30, 'reason' => 'x']);
        $this->actingAs($admin)->post("/admin/users/{$target->id}/revoke")->assertRedirect();

        $target->refresh();

        $this->assertNull($target->comped_until);
        $this->assertSame(State::Free, $target->billingState());
        $this->assertTrue(
            AdminAction::where('target_user_id', $target->id)->where('action', AdminAction::REVOKED_ACCESS)->exists(),
        );
    }

    /** A comp must not override money the athlete is actually paying. */
    public function test_a_paying_subscriber_is_not_relabelled_as_complimentary(): void
    {
        $admin = $this->admin();
        $target = User::factory()->free()->create();
        $this->subscribe($target);

        $this->actingAs($admin)->post("/admin/users/{$target->id}/grant", ['days' => 30, 'reason' => 'x']);

        $this->assertSame(State::Subscribed, $target->fresh()->billingState());
    }

    // ---------------------------------------------------------- cancellation

    public function test_cancelling_a_subscription_requires_typing_the_email(): void
    {
        $admin = $this->admin();
        $target = User::factory()->free()->create();
        $this->subscribe($target);

        $this->actingAs($admin)
            ->post("/admin/users/{$target->id}/cancel-subscription", ['confirm' => 'wrong@example.test'])
            ->assertSessionHasErrors('confirm');

        $this->assertFalse(AdminAction::where('action', AdminAction::CANCELLED_SUBSCRIPTION)->exists());
    }

    // ------------------------------------------------------------- disclosure

    /**
     * An admin needs to know whether a key is set, never what it is. Reading
     * someone's credential is not support, and a page that can render one is a
     * page that can leak it.
     */
    public function test_the_admin_page_never_discloses_stored_secrets(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        $target->forceFill(['hevy_api_key' => 'hevy-secret-value'])->save();
        $target->aiCredentials()->create([
            'provider' => \App\Services\AI\ProviderRegistry::OPENAI,
            'api_key' => 'sk-provider-secret',
            'model' => 'gpt-4o',
            'base_url' => 'https://api.openai.com/v1',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->get("/admin/users/{$target->id}")
            ->assertOk()
            ->assertDontSee('hevy-secret-value')
            ->assertDontSee('sk-provider-secret')
            // …but does say whether one is set, which is the useful part.
            ->assertSee(__('app.admin.present'));
    }

    /** An audit trail an admin can quietly edit is not an audit trail. */
    public function test_admin_actions_record_who_did_what_to_whom(): void
    {
        $admin = $this->admin();
        $target = User::factory()->free()->create();

        $this->actingAs($admin)->post("/admin/users/{$target->id}/grant", ['days' => 7, 'reason' => 'Beta tester']);

        $this->actingAs($admin)->get("/admin/users/{$target->id}")
            ->assertOk()
            ->assertSee(__('app.admin.actions.granted_access'))
            ->assertSee($admin->name)
            ->assertSee('Beta tester');
    }
}
