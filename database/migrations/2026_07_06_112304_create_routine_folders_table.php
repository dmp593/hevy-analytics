<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routine_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('hevy_id')->index();
            $table->string('title');
            $table->integer('index')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'hevy_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_folders');
    }
};
