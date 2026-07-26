<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user interface language.
 *
 * Kept alongside timezone because they answer the same kind of question — how
 * this person expects to read their own data — and both have to be known before
 * a page renders. Nullable rather than defaulted: null means "follow the
 * browser", which is the right behaviour for someone who has never chosen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 10)->nullable()->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
