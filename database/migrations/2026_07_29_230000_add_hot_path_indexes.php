<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The scalability audit's index list: Postgres does not index plain FKs,
 * and these columns sit on every-request queries (sync status on each
 * dashboard, active goal three times per page) or on unbounded tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_logs', function (Blueprint $table) {
            $table->index(['user_id', 'id']);
        });
        Schema::table('goals', function (Blueprint $table) {
            $table->index('user_id');
        });
        Schema::table('routine_exercises', function (Blueprint $table) {
            $table->index('routine_id');
        });
        Schema::table('progress_photos', function (Blueprint $table) {
            $table->index('user_id');
        });
        Schema::table('write_operations', function (Blueprint $table) {
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('sync_logs', fn (Blueprint $t) => $t->dropIndex(['user_id', 'id']));
        Schema::table('goals', fn (Blueprint $t) => $t->dropIndex(['user_id']));
        Schema::table('routine_exercises', fn (Blueprint $t) => $t->dropIndex(['routine_id']));
        Schema::table('progress_photos', fn (Blueprint $t) => $t->dropIndex(['user_id']));
        Schema::table('write_operations', fn (Blueprint $t) => $t->dropIndex(['user_id']));
    }
};
