<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false);

            // Complimentary access, granted by an admin and separate from the
            // signup trial. Kept as its own column so extending someone's access
            // for support reasons cannot be mistaken later for them having been
            // on a trial all along.
            $table->timestamp('comped_until')->nullable();
            $table->string('comped_reason')->nullable();
        });

        // Anything an admin does to someone else's account is recorded.
        //
        // An admin can grant free access, cancel a paid subscription and read
        // account state. Without a record, "who gave this account free access
        // for a year" has no answer — and the person best placed to abuse it is
        // the person who would otherwise be checking.
        Schema::create('admin_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action');
            $table->text('detail')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['target_user_id', 'created_at']);
            $table->index(['admin_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_actions');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['is_admin', 'comped_until', 'comped_reason']));
    }
};
