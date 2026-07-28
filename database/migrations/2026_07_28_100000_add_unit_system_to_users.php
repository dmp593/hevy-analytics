<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 'metric' or 'imperial'. Applies to body data only (height,
            // bodyweight, tape measurements); training loads are kg everywhere.
            $table->string('unit_system', 8)->default('metric');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('unit_system');
        });
    }
};
