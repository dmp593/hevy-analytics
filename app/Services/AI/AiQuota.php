<?php

namespace App\Services\AI;

use App\Models\AiAnalysis;
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

    public function usedThisMonth(): int
    {
        return $this->user->aiAnalyses()
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
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
            return 'AI analysis is temporarily unavailable while we top up capacity. Please try again later.';
        }

        if ($this->remaining() <= 0) {
            return sprintf(
                'You have used all %d AI analyses for this month. Your allowance resets on %s.',
                $this->monthlyLimit(),
                Carbon::now()->addMonth()->startOfMonth()->format('j M'),
            );
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

        return AiAnalysis::where('created_at', '>=', Carbon::now()->startOfMonth())->count() >= $ceiling;
    }
}
