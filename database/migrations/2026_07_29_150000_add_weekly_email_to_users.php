<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The weekly check-in email: an opt-out flag (on by default, honestly easy
 * to turn off) and the send watermark that makes the command idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('weekly_email')->default(true);
            $table->timestamp('weekly_checkin_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['weekly_email', 'weekly_checkin_sent_at']);
        });
    }
};
