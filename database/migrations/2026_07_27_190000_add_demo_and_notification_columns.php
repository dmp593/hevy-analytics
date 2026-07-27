<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // The public demonstration account. A column rather than a config
            // email, so the read-only rule cannot be dodged by registering an
            // account with a similar address — only the seeder sets this.
            $table->boolean('is_demo')->default(false);

            // Notification watermarks. Each email must be sent exactly once per
            // occurrence, and "did we already tell them" has to survive a queue
            // retry, a second scheduler run and a redeploy — so it lives on the
            // row, not in memory.
            $table->timestamp('trial_ending_notified_at')->nullable();
            $table->timestamp('past_due_notified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn([
            'is_demo', 'trial_ending_notified_at', 'past_due_notified_at',
        ]));
    }
};
