<?php

namespace App\Http\Controllers\Admin;

use App\Billing\State;
use App\Http\Controllers\Controller;
use App\Models\AdminAction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /** Page size. Large enough to be useful, small enough not to be a table dump. */
    private const PER_PAGE = 25;

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $state = $request->query('state');

        $users = User::query()
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            }))
            // Eager loaded because billingState() reads them per row, and this
            // page is a list — without it, one query per user per column.
            ->with(['subscriptions.items', 'customer'])
            ->withCount('workouts')
            ->orderByDesc('created_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        // Filtered after loading rather than in SQL: billing state is derived
        // from several columns plus Cashier's own rules, and duplicating those
        // rules as a WHERE clause is how the admin list and the app itself end
        // up disagreeing about who is subscribed.
        if ($state) {
            $users->setCollection(
                $users->getCollection()->filter(fn (User $u) => $u->billingState()->value === $state)->values()
            );
        }

        return view('admin.users.index', [
            'users' => $users,
            'search' => $search,
            'state' => $state,
            'states' => State::cases(),
            'counts' => $this->counts(),
        ]);
    }

    public function show(User $user)
    {
        return view('admin.users.show', [
            'user' => $user->loadCount(['workouts', 'bodyMeasurements', 'aiUsageEvents']),
            'state' => $user->billingState(),
            'entitlements' => $user->entitlements(),
            'subscription' => $user->subscription(),
            'actions' => AdminAction::where('target_user_id', $user->id)
                ->with('admin')
                ->latest()
                ->limit(50)
                ->get(),
        ]);
    }

    /**
     * Grant complimentary access for a period.
     *
     * Deliberately time-boxed rather than a permanent flag: an unbounded grant
     * is one nobody ever revisits, and "why does this account have free access"
     * should have an answer with an end date on it.
     */
    public function grant(Request $request, User $user)
    {
        $data = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:3650'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $until = now()->addDays($data['days']);

        $user->forceFill([
            'comped_until' => $until,
            'comped_reason' => $data['reason'],
        ])->save();

        AdminAction::record(
            $request->user(),
            $user,
            AdminAction::GRANTED_ACCESS,
            __('app.admin.granted_detail', ['days' => $data['days'], 'until' => $until->toDateString(), 'reason' => $data['reason']]),
        );

        return back()->with('status', __('app.admin.granted', ['name' => $user->name]));
    }

    public function revoke(Request $request, User $user)
    {
        abort_unless($user->comped_until !== null, 404);

        $user->forceFill(['comped_until' => null, 'comped_reason' => null])->save();

        AdminAction::record($request->user(), $user, AdminAction::REVOKED_ACCESS);

        return back()->with('status', __('app.admin.revoked', ['name' => $user->name]));
    }

    /**
     * Cancel a real, paid subscription on the athlete's behalf.
     *
     * Cancels at period end, exactly as the athlete's own cancel button does.
     * A support request to cancel is not a reason to take away time already
     * paid for, and cancelling immediately would also mean issuing a refund
     * nobody asked for.
     */
    public function cancelSubscription(Request $request, User $user)
    {
        $request->validate(['confirm' => ['required', Rule::in([$user->email])]]);

        $subscription = $user->subscription();

        abort_unless($subscription && $subscription->valid(), 404);

        $subscription->cancel();

        AdminAction::record(
            $request->user(),
            $user,
            AdminAction::CANCELLED_SUBSCRIPTION,
            __('app.admin.cancelled_detail', ['id' => $subscription->paddle_id]),
        );

        return back()->with('status', __('app.admin.cancelled', ['name' => $user->name]));
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        $counts = array_fill_keys(array_column(State::cases(), 'value'), 0);

        User::with(['subscriptions.items'])
            ->select(['id', 'trial_ends_at', 'comped_until'])
            ->chunk(500, function ($chunk) use (&$counts) {
                foreach ($chunk as $user) {
                    $counts[$user->billingState()->value]++;
                }
            });

        return $counts;
    }
}
