<?php

namespace App\Models;

use App\Billing\Entitlements;
use App\Billing\State;
use App\Casts\TolerantEncrypted;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Paddle\Billable;

#[Fillable(['name', 'email', 'password', 'hevy_api_key', 'sex', 'age', 'height_cm', 'unit_system', 'activity_level', 'timezone', 'locale', 'body_fat_source', 'hevy_last_synced_at'])]
#[Hidden(['password', 'remember_token', 'hevy_api_key'])]
/**
 * Implementing MustVerifyEmail is what makes the `verified` middleware do
 * anything: Laravel's EnsureEmailIsVerified passes straight through for a user
 * that does not implement it, so every route in the verified group was
 * unguarded and anyone could sign up with an address they did not own.
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use Billable, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'comped_until' => 'datetime',
            'is_admin' => 'boolean',
            'is_demo' => 'boolean',
            'weekly_email' => 'boolean',
            'trial_ending_notified_at' => 'datetime',
            'past_due_notified_at' => 'datetime',
            'password' => 'hashed',
            'hevy_api_key' => TolerantEncrypted::class,
            'fatsecret_token' => TolerantEncrypted::class,
            'fatsecret_secret' => TolerantEncrypted::class,
            'fatsecret_linked_at' => 'datetime',
            'fatsecret_synced_at' => 'datetime',
            'height_cm' => 'float',
            'activity_level' => 'float',
            'age' => 'integer',
            'hevy_last_synced_at' => 'datetime',
        ];
    }

    public function hasHevyKey(): bool
    {
        return filled($this->hevy_api_key);
    }

    /**
     * The zone this athlete's days and weeks are measured in.
     *
     * Deliberately NOT named timezone(): Eloquent treats a method whose name
     * matches an attribute as a relationship accessor, which sends
     * $this->timezone straight back into this method.
     *
     * Always returns something usable — a stale or hand-edited value must not be
     * able to throw from inside an analytics calculation.
     */
    public function resolvedTimezone(): string
    {
        $tz = $this->getAttribute('timezone') ?: 'UTC';

        return in_array($tz, timezone_identifiers_list(), true) ? $tz : 'UTC';
    }

    public function exerciseTemplates(): HasMany
    {
        return $this->hasMany(ExerciseTemplate::class);
    }

    public function workouts(): HasMany
    {
        return $this->hasMany(Workout::class);
    }

    public function routines(): HasMany
    {
        return $this->hasMany(Routine::class);
    }

    public function routineFolders(): HasMany
    {
        return $this->hasMany(RoutineFolder::class);
    }

    public function bodyMeasurements(): HasMany
    {
        return $this->hasMany(BodyMeasurement::class);
    }

    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }

    /** Memoized per request: the dashboard asks three services this question. */
    private ?Goal $activeGoalMemo = null;

    private bool $activeGoalResolved = false;

    public function activeGoal(): ?Goal
    {
        if ($this->activeGoalResolved) {
            return $this->activeGoalMemo;
        }
        $this->activeGoalResolved = true;

        return $this->activeGoalMemo = (function (): ?Goal {
            return $this->goals()->where('is_active', true)->latest('id')->first()
                ?? $this->goals()->latest('id')->first();
        })();
    }

    public function nutritionTargets(): HasMany
    {
        return $this->hasMany(NutritionTarget::class);
    }

    public function intakeLogs(): HasMany
    {
        return $this->hasMany(IntakeLog::class);
    }

    public function progressPhotos(): HasMany
    {
        return $this->hasMany(ProgressPhoto::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(SyncLog::class);
    }

    public function writeOperations(): HasMany
    {
        return $this->hasMany(WriteOperation::class);
    }

    /**
     * Whether an admin has granted this account free access.
     *
     * comped_reason is the marker — every comp must have one, so a row with a
     * reason and no expiry is deliberate rather than a half-written record.
     * comped_until is optional: null means indefinite.
     */
    public function isComped(): bool
    {
        if (blank($this->comped_reason)) {
            return false;
        }

        return $this->comped_until === null || $this->comped_until->isFuture();
    }

    /** What this athlete is allowed to see, given their billing state. */
    public function entitlements(): Entitlements
    {
        return Entitlements::for($this);
    }

    public function billingState(): State
    {
        return State::for($this);
    }

    /**
     * The name and email Paddle should show at checkout.
     *
     * Cashier reads this when creating the customer, and without it Paddle asks
     * for an email the athlete has already given us.
     */
    public function paddleName(): string
    {
        return $this->name;
    }

    public function paddleEmail(): string
    {
        return $this->email;
    }

    /** The athlete's own AI provider keys, encrypted at rest. */
    public function aiCredentials(): HasMany
    {
        return $this->hasMany(AiCredential::class);
    }

    public function aiAnalyses(): HasMany
    {
        return $this->hasMany(AiAnalysis::class);
    }

    public function aiUsageEvents(): HasMany
    {
        return $this->hasMany(AiUsageEvent::class);
    }
}
