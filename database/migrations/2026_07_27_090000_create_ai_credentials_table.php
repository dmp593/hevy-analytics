<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('provider');
            // Encrypted at rest by the model cast, so this must be text rather
            // than a sized string: Laravel's ciphertext is far longer than the
            // key it wraps and a varchar(255) would truncate it into garbage.
            $table->text('api_key');
            $table->string('model');
            $table->string('base_url');

            // One row per user is the current product rule, but storing the flag
            // rather than deleting the old row means switching provider keeps the
            // previous key, so an athlete can move back without re-pasting it.
            $table->boolean('is_active')->default(true);

            // Set when a call actually succeeds. A key that has never worked and
            // one that stopped working look identical without this.
            $table->timestamp('last_verified_at')->nullable();
            $table->string('last_error')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'provider']);
            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_credentials');
    }
};
