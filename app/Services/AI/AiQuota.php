<?php

namespace App\Services\AI;

use App\Models\AiUsageEvent;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Hard caps on AI generation.
 *
 * Rate limiting alone only slows abuse down; it does not bound it. With
 * unlimited free accounts each able to force a fresh uncached call, the only
 * thing between a script and the operator's API balance was a per-minute
 * throttle. These are counters, not rates: once the month's allowance is spent
 * the answer is no until the month rolls over.
 *
 * This is deliberately plan-agnostic. When subscriptions land the per-user
 * allowance becomes a plan entitlement; the shape of the check does not change.
 */
class AiQuota
{
    public function __construct(private readonly User $user) {}

    public function monthlyLimit(): int
    {
        return (int) config('services.ai.monthly_limit_per_user', 30);
    }

    /**
     * Counts requests ATTEMPTED, not analyses stored. A provider response that
     * is 200 but empty, or any failure after the tokens were spent, costs money
     * and produces no analysis — counting results let ten forced calls consume
     * zero allowance.
     */
    public function usedThisMonth(): int
    {
        return $this->user->aiUsageEvents()
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            // Only calls billed to the operator. An athlete spending their own
            // API key is not spending ours, so rationing them would be charging
            // twice for the same request.
            ->where('billed_to_app', true)
            ->count();
    }

    public function remaining(): int
    {
        return max(0, $this->monthlyLimit() - $this->usedThisMonth());
    }

    public function allows(): bool
    {
        return $this->remaining() > 0 && ! self::globalCeilingReached();
    }

    /**
     * Reason the request was refused, for showing the user. Null when allowed.
     */
    public function denialReason(): ?string
    {
        if (self::globalCeilingReached()) {
            return __('app.ai.temporarily_unavailable');
        }

        if ($this->remaining() <= 0) {
            return __('app.ai.quota_spent', [
                'limit' => $this->monthlyLimit(),
                // startOfMonth BEFORE addMonth: Carbon overflows 31 Jan + 1 month
                // to 3 March, which would report the reset a month late.
                'date' => Carbon::now()->startOfMonth()->addMonth()->isoFormat('D MMM'),
            ]);
        }

        return null;
    }

    /**
     * App-wide circuit breaker. Without this a single bad month across all users
     * bills the operator with no upper bound; better to degrade the feature than
     * to discover the spend on an invoice.
     */
    public static function globalCeilingReached(): bool
    {
        $ceiling = (int) config('services.ai.monthly_limit_global', 0);

        if ($ceiling <= 0) {
            return false;
        }

        return AiUsageEvent::where('created_at', '>=', Carbon::now()->startOfMonth())
            ->where('billed_to_app', true)
            ->count() >= $ceiling;
    }
}
