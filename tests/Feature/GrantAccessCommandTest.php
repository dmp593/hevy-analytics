<?php

namespace Tests\Feature;

use App\Billing\State;
use App\Models\AdminAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The console route to complimentary access.
 *
 * It exists for the case the admin UI cannot serve: a fresh install where there
 * is no admin account yet, and the operator wants to use their own product. So
 * the tests care most about the two things that would make it useless there —
 * needing an admin to exist, and the grant having an expiry date.
 */
class GrantAccessCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_grants_access_that_never_expires_by_default(): void
    {
        $user = User::factory()->free()->create(['email' => 'owner@example.test']);

        $this->artisan('app:grant-access owner@example.test --reason="My own account"')
            ->expectsOutputToContain('no end date')
            ->assertSuccessful();

        $user->refresh();

        $this->assertNull($user->comped_until);
        $this->assertSame('My own account', $user->comped_reason);
        $this->assertSame(State::Complimentary, $user->billingState());
        $this->assertNull($user->entitlements()->historyDays());
    }

    /** No admin account exists here at all — that is the point of the command. */
    public function test_it_works_with_no_admin_account_in_the_database(): void
    {
        User::factory()->free()->create(['email' => 'owner@example.test']);

        $this->assertSame(0, User::where('is_admin', true)->count());

        $this->artisan('app:grant-access owner@example.test')->assertSuccessful();

        $action = AdminAction::firstOrFail();

        // Null, not attributed to somebody who did not do it.
        $this->assertNull($action->admin_id);
        $this->assertSame(AdminAction::GRANTED_ACCESS, $action->action);
    }

    public function test_days_bounds_the_grant(): void
    {
        $user = User::factory()->free()->create(['email' => 'tester@example.test']);

        $this->artisan('app:grant-access tester@example.test --days=14 --reason=Beta')
            ->assertSuccessful();

        $user->refresh();

        $this->assertSame(14, (int) round(now()->diffInDays($user->comped_until, false)));
        $this->assertSame(State::Complimentary, $user->billingState());

        $this->travel(15)->days();

        $this->assertSame(State::Free, $user->fresh()->billingState());
    }

    public function test_a_nonsense_length_is_refused_rather_than_read_as_zero(): void
    {
        $user = User::factory()->free()->create(['email' => 'tester@example.test']);

        $this->artisan('app:grant-access tester@example.test --days=soon')->assertFailed();
        $this->artisan('app:grant-access tester@example.test --days=0')->assertFailed();
        $this->artisan('app:grant-access tester@example.test --days=-5')->assertFailed();

        $this->assertFalse($user->fresh()->isComped());
    }

    public function test_an_unknown_email_fails_loudly(): void
    {
        $this->artisan('app:grant-access nobody@example.test')
            ->expectsOutputToContain('No account')
            ->assertFailed();
    }

    public function test_revoking_clears_the_grant_and_is_recorded(): void
    {
        $user = User::factory()->free()->create(['email' => 'owner@example.test']);

        $this->artisan('app:grant-access owner@example.test --reason=Owner')->assertSuccessful();
        $this->artisan('app:grant-access owner@example.test --revoke')->assertSuccessful();

        $user->refresh();

        $this->assertNull($user->comped_until);
        $this->assertNull($user->comped_reason);
        $this->assertSame(State::Free, $user->billingState());
        $this->assertTrue(AdminAction::where('action', AdminAction::REVOKED_ACCESS)->exists());
    }

    /** Revoking nothing is not an error, but it must not claim it did something. */
    public function test_revoking_when_there_is_nothing_to_revoke_says_so(): void
    {
        User::factory()->free()->create(['email' => 'owner@example.test']);

        $this->artisan('app:grant-access owner@example.test --revoke')
            ->expectsOutputToContain('no complimentary access')
            ->assertSuccessful();

        $this->assertSame(0, AdminAction::count());
    }
}
