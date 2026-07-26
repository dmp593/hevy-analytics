<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('write_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('operation'); // e.g. workout.create, routine.update
            $table->string('method');
            $table->string('endpoint');
            $table->json('payload')->nullable();
            $table->json('response')->nullable();
            $table->integer('status_code')->nullable();
            $table->string('status')->default('pending'); // pending|confirmed|success|failed|reverted
            $table->string('idempotency_key')->nullable()->index();
            $table->json('revert_info')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('write_operations');
    }
};
