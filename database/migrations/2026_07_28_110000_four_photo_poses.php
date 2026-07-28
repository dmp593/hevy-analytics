<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Progress photos grow from three poses to four: "side" splits into left
     * and right. Existing side photos become "left" — an arbitrary but stable
     * choice the athlete can correct by re-uploading under the other pose.
     */
    public function up(): void
    {
        DB::table('progress_photos')->where('angle', 'side')->update(['angle' => 'left']);
    }

    public function down(): void
    {
        DB::table('progress_photos')->whereIn('angle', ['left', 'right'])->update(['angle' => 'side']);
    }
};
