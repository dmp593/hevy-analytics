<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('hevy_id')->index();
            $table->string('title')->nullable();
            $table->string('routine_hevy_id')->nullable()->index();
            $table->text('description')->nullable();
            $table->timestamp('start_time')->nullable()->index();
            $table->timestamp('end_time')->nullable();
            $table->timestamp('hevy_created_at')->nullable();
            $table->timestamp('hevy_updated_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'hevy_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workouts');
    }
};
