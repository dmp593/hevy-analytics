<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Let the audit log record things done from the server console.
 *
 * The first complimentary grant has the same chicken-and-egg problem as the
 * first admin: it happens before there is an admin account to attribute it to.
 * A null admin_id means "done from the shell", which is a truthful answer —
 * more truthful than attributing it to whichever account happened to be handy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_actions', function (Blueprint $table) {
            $table->foreignId('admin_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Rows written from the console have no admin to point at, so they are
        // removed rather than reassigned to somebody who did not do them.
        DB::table('admin_actions')->whereNull('admin_id')->delete();

        Schema::table('admin_actions', function (Blueprint $table) {
            $table->foreignId('admin_id')->nullable(false)->change();
        });
    }
};
