<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routine_exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routine_id')->constrained()->cascadeOnDelete();
            $table->integer('index')->default(0);
            $table->string('title')->nullable();
            $table->string('exercise_template_hevy_id')->nullable()->index();
            $table->integer('superset_id')->nullable();
            $table->integer('rest_seconds')->nullable();
            $table->text('notes')->nullable();
            $table->json('sets')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_exercises');
    }
};
