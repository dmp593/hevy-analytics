<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_usage_events', function (Blueprint $table) {
            // Whether this call spent the operator's money. Calls on an
            // athlete's own key are still recorded as their history, but must
            // not consume an allowance that exists to bound the operator's bill.
            //
            // Defaults true so existing rows — all of which predate per-user
            // keys and were therefore billed to the app — keep counting.
            $table->boolean('billed_to_app')->default(true);
        });

        Schema::table('ai_analyses', function (Blueprint $table) {
            // Which provider produced this. Previously implicit, because there
            // was only ever one.
            $table->string('provider')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ai_usage_events', fn (Blueprint $t) => $t->dropColumn('billed_to_app'));
        Schema::table('ai_analyses', fn (Blueprint $t) => $t->dropColumn('provider'));
    }
};
