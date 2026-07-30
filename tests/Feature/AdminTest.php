<?php

namespace Tests\Feature;

use App\Billing\State;
use App\Models\AdminAction;
use App\Models\User;
use App\Services\AI\ProviderRegistry;
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

    /**
     * The counts run off a narrowed select, and a column left out of it reads
     * as null rather than raising — so a comped account would be quietly filed
     * as free and nobody would ever notice the number was wrong.
     */
    public function test_the_state_filter_and_counts_see_comped_accounts(): void
    {
        $admin = $this->admin();

        User::factory()->free()->create([
            'name' => 'Comped Athlete',
            'comped_reason' => 'Owner',
            'comped_until' => null,
        ]);
        User::factory()->free()->create(['name' => 'Paying Nobody']);

        $this->actingAs($admin)->get('/admin/users?state=comped')
            ->assertOk()
            ->assertSee('Comped Athlete')
            ->assertDontSee('Paying Nobody');

        $counts = $this->actingAs($admin)->get('/admin/users')->viewData('counts');

        $this->assertSame(1, $counts[State::Complimentary->value]);
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

    /**
     * The reason is what is mandatory. Without one there is no answer to "why
     * does this account have free access", which is the whole point of the log.
     */
    public function test_a_grant_must_have_a_reason(): void
    {
        $admin = $this->admin();
        $target = User::factory()->free()->create();

        $this->actingAs($admin)->post("/admin/users/{$target->id}/grant", ['days' => 30])
            ->assertSessionHasErrors('reason');

        $this->actingAs($admin)->post("/admin/users/{$target->id}/grant", ['days' => 99999, 'reason' => 'x'])
            ->assertSessionHasErrors('days');

        $this->assertNull($target->fresh()->comped_until);
        $this->assertFalse($target->fresh()->isComped());
    }

    /**
     * The operator's own account is the reason this exists: an access that
     * quietly lapses locks you out of your own product, and every dated grant
     * eventually becomes one.
     */
    public function test_a_grant_with_no_days_never_expires(): void
    {
        $admin = $this->admin();
        $target = User::factory()->free()->create();

        $this->actingAs($admin)
            ->post("/admin/users/{$target->id}/grant", ['reason' => 'My own account'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $target->refresh();

        $this->assertNull($target->comped_until);
        $this->assertTrue($target->isComped());
        $this->assertSame(State::Complimentary, $target->billingState());
        $this->assertNull($target->entitlements()->historyDays());
        $this->assertTrue($target->entitlements()->canUseAi());

        // Ten years on, still comped — that is what "no end date" has to mean.
        $this->travel(10)->years();
        $this->assertSame(State::Complimentary, $target->fresh()->billingState());
    }

    /** A grant that has run out is a free account again, not a comped one. */
    public function test_an_expired_grant_stops_granting_anything(): void
    {
        $target = User::factory()->free()->create([
            'comped_until' => now()->subDay(),
            'comped_reason' => 'Expired support case',
        ]);

        $this->assertFalse($target->isComped());
        $this->assertSame(State::Free, $target->billingState());
    }

    /**
     * comped_reason is the marker. A row with a date and no reason is a
     * half-written record, not a grant — most likely a leftover from a partial
     * write, and it should not silently hand out the product.
     */
    public function test_a_date_with_no_reason_is_not_a_grant(): void
    {
        $target = User::factory()->free()->create([
            'comped_until' => now()->addYear(),
            'comped_reason' => null,
        ]);

        $this->assertFalse($target->isComped());
        $this->assertSame(State::Free, $target->billingState());
    }

    /** An indefinite grant is still revocable — the form must offer the button. */
    public function test_an_indefinite_grant_can_be_revoked(): void
    {
        $admin = $this->admin();
        $target = User::factory()->free()->create();

        $this->actingAs($admin)->post("/admin/users/{$target->id}/grant", ['reason' => 'Forever']);

        $this->actingAs($admin)->get("/admin/users/{$target->id}")
            ->assertOk()
            ->assertSee(__('app.admin.comped_forever'))
            ->assertSee(__('app.admin.revoke'));

        $this->actingAs($admin)->post("/admin/users/{$target->id}/revoke")->assertRedirect();

        $this->assertFalse($target->fresh()->isComped());
        $this->assertSame(State::Free, $target->fresh()->billingState());
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
            'provider' => ProviderRegistry::OPENAI,
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

    public function test_bootstrap_promotes_an_existing_account_named_as_admin(): void
    {
        $user = User::factory()->create(['email' => 'ops@example.com', 'is_admin' => false]);

        // Laravel's env() reads $_ENV/$_SERVER, not putenv() alone.
        foreach (['BOOTSTRAP_ADMIN_EMAIL' => 'ops@example.com', 'BOOTSTRAP_ADMIN_PASSWORD' => 'irrelevant-here'] as $k => $v) {
            $_ENV[$k] = $_SERVER[$k] = $v;
            putenv("{$k}={$v}");
        }

        try {
            $this->artisan('app:bootstrap-accounts')->assertSuccessful();
        } finally {
            foreach (['BOOTSTRAP_ADMIN_EMAIL', 'BOOTSTRAP_ADMIN_PASSWORD'] as $k) {
                unset($_ENV[$k], $_SERVER[$k]);
                putenv($k);
            }
        }

        $this->assertTrue($user->fresh()->is_admin);
    }

    public function test_bootstrap_restores_the_operator_comp_on_an_existing_account(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $this->assertNull($user->comped_reason);

        foreach (['BOOTSTRAP_OWNER_EMAIL' => 'owner@example.com', 'BOOTSTRAP_OWNER_PASSWORD' => 'irrelevant-here'] as $k => $v) {
            $_ENV[$k] = $_SERVER[$k] = $v;
            putenv("{$k}={$v}");
        }

        try {
            $this->artisan('app:bootstrap-accounts')->assertSuccessful();
        } finally {
            foreach (['BOOTSTRAP_OWNER_EMAIL', 'BOOTSTRAP_OWNER_PASSWORD'] as $k) {
                unset($_ENV[$k], $_SERVER[$k]);
                putenv($k);
            }
        }

        $fresh = $user->fresh();
        $this->assertNotNull($fresh->comped_reason);
        $this->assertNull($fresh->comped_until);
        $this->assertFalse($fresh->is_admin); // owner is not silently made admin
    }
}
