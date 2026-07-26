<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('hevy_id')->index();
            $table->string('title');
            $table->string('folder_hevy_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('hevy_created_at')->nullable();
            $table->timestamp('hevy_updated_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'hevy_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routines');
    }
};
