<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // OAuth 1.0 token pair for a linked fatsecret.com profile,
            // encrypted at rest (TolerantEncrypted casts on the model).
            $table->text('fatsecret_token')->nullable();
            $table->text('fatsecret_secret')->nullable();
            $table->timestamp('fatsecret_linked_at')->nullable();
            $table->timestamp('fatsecret_synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['fatsecret_token', 'fatsecret_secret', 'fatsecret_linked_at', 'fatsecret_synced_at']);
        });
    }
};
