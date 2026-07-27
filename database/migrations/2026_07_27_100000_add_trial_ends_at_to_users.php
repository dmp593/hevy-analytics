<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The card-free trial, held entirely on our side.
     *
     * Cashier has a "generic trial" for this, but it lives on the customers
     * table and its createAsCustomer() calls the Paddle API to fill in the
     * required paddle_id. That would mean every registration makes a network
     * call to Paddle, creates a billing customer for people who may never pay,
     * and fails if Paddle is unreachable. Sign-up must not depend on the payment
     * provider being up, so the trial lives here instead.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('trial_ends_at')->nullable();
        });

        // Everyone here predates billing, so they get the full trial from today
        // rather than being dropped onto the free tier by a deploy they did not
        // ask for and never agreed to.
        DB::table('users')->whereNull('trial_ends_at')->update([
            'trial_ends_at' => now()->addDays((int) config('billing.trial_days', 14)),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('trial_ends_at'));
    }
};
