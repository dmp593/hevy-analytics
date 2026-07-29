<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily steps and sleep on the intake log: it is already the one-row-per-day
 * table, and both numbers are context for the same questions nutrition
 * answers (does the stated activity level match reality; is recovery there).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intake_logs', function (Blueprint $table) {
            $table->unsignedInteger('steps')->nullable()->after('carb_g');
            $table->unsignedSmallInteger('sleep_minutes')->nullable()->after('steps');
        });
    }

    public function down(): void
    {
        Schema::table('intake_logs', function (Blueprint $table) {
            $table->dropColumn(['steps', 'sleep_minutes']);
        });
    }
};
