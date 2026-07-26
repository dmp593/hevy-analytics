<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A ledger of AI calls ATTEMPTED, which is what the quota must count.
 *
 * Counting saved analyses instead let a caller burn the operator's balance for
 * free: a provider response that is 200 but empty, or any error after the tokens
 * were spent, bills us and creates no analysis row, so the allowance never moved.
 * Ten forced calls consumed zero quota.
 *
 * One row per outbound request, written before the call, whatever the outcome.
 * This is also the foundation the per-plan metering in the billing work will read.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('scope');
            $table->string('provider')->default('deepseek');
            $table->string('model')->nullable();
            $table->string('outcome')->default('attempted'); // attempted|success|failed
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->timestamps();

            // The quota query: this user, this month.
            $table->index(['user_id', 'created_at']);
            // The global circuit breaker: everyone, this month.
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_events');
    }
};
