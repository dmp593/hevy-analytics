<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user timezone.
 *
 * Workout timestamps are stored in UTC, but every headline metric in this app is
 * per WEEK or per DAY — and which week a set belongs to depends on where the
 * athlete lives. A 21:00 session in Lisbon during summer is 20:00 UTC and lands
 * on the right day by luck; the same session in Auckland is the previous day in
 * UTC, so a Monday workout counts toward the week before. Bucketing in UTC
 * silently misattributes sessions near midnight and at week boundaries, which
 * corrupts exactly the sets-per-week figure the product is built on.
 *
 * Defaults to UTC so behaviour is unchanged until a user picks a zone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('timezone', 64)->default('UTC')->after('activity_level');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
