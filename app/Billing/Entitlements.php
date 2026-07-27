<?php

namespace App\Billing;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * What this athlete is allowed to see and do.
 *
 * Everything that depends on whether someone is paying goes through here, so
 * "are they entitled to this" is answered in one place rather than as an
 * `if ($user->subscribed())` scattered through eleven controllers — which is how
 * one of them ends up checking the wrong thing and quietly giving the product
 * away, or worse, quietly withholding it from someone who paid.
 *
 * The billing provider does not appear in this file. Cashier answers "is there
 * an active subscription"; this class answers "what does that mean". Swapping
 * Paddle for something else should not reach past State.
 */
class Entitlements
{
    public function __construct(private readonly User $user) {}

    public static function for(User $user): self
    {
        return new self($user);
    }

    public function state(): State
    {
        return State::for($this->user);
    }

    /**
     * How far back analysis may look, or null for no limit.
     *
     * This is the wall. It is not a feature toggle: every page still works and
     * every metric still exists, they are computed over a shorter window. An
     * athlete on the free tier is not shown a locked door where a number used to
     * be, they are shown the number their history supports.
     */
    public function historyDays(): ?int
    {
        return $this->config()['history_days'];
    }

    /**
     * The earliest date analysis may consider, or null when unlimited.
     *
     * Callers should clamp their own window to this rather than replace it: a
     * request for the last 7 days must stay 7 days, not widen to 30.
     */
    public function historyFloor(): ?Carbon
    {
        $days = $this->historyDays();

        return $days === null ? null : Carbon::now()->subDays($days)->startOfDay();
    }

    /** Narrow a requested start date so it never reaches past the entitlement. */
    public function clampFrom(?Carbon $requested): ?Carbon
    {
        $floor = $this->historyFloor();

        if ($floor === null) {
            return $requested;
        }

        if ($requested === null) {
            return $floor;
        }

        return $requested->lessThan($floor) ? $floor->copy() : $requested;
    }

    /** True when the athlete asked for more history than they are entitled to. */
    public function historyWasClamped(?Carbon $requested): bool
    {
        $floor = $this->historyFloor();

        if ($floor === null) {
            return false;
        }

        return $requested === null || $requested->lessThan($floor);
    }

    public function aiAnalysesPerMonth(): int
    {
        return $this->config()['ai_analyses_per_month'];
    }

    public function progressPhotos(): int
    {
        return $this->config()['progress_photos'];
    }

    /**
     * Whether AI analysis is available at all.
     *
     * An athlete using their own provider key is spending their own account, so
     * the allowance is irrelevant to them — refusing on plan grounds would be
     * withholding something that costs us nothing.
     */
    public function canUseAi(): bool
    {
        if ($this->user->aiCredentials()->where('is_active', true)->exists()) {
            return true;
        }

        return $this->aiAnalysesPerMonth() > 0;
    }

    /**
     * Data export is never gated.
     *
     * It is a right under GDPR Article 20, not a feature, and holding someone's
     * own training history hostage to a subscription would be both unlawful and
     * a genuinely nasty thing to do.
     */
    public function canExportData(): bool
    {
        return true;
    }

    /** @return array{history_days: ?int, ai_analyses_per_month: int, progress_photos: int} */
    private function config(): array
    {
        $key = $this->state()->configKey();

        return config("billing.entitlements.{$key}", config('billing.entitlements.free'));
    }
}
