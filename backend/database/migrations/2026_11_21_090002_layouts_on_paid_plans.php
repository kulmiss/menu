<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Layouts" (owner, 2026-11-21) is for Plus and up: on Free a restaurant
 * keeps its style's own layout or the text layout (no photos). A plan without
 * a row uses the default (on), so only the Free plan gets an "off" row. Runs
 * with the code that adds the setting, so no restaurant has picked one yet
 * (the Super Admin can change it per plan later).
 */
return new class extends Migration
{
    public function up(): void
    {
        $free = DB::table('plans')->where('slug', 'free')->value('id');
        if ($free === null) {
            return;
        }
        DB::table('plan_features')->updateOrInsert(['plan_id' => $free, 'feature_key' => 'layouts'], ['enabled' => false, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        $free = DB::table('plans')->where('slug', 'free')->value('id');
        DB::table('plan_features')->where('plan_id', $free)->where('feature_key', 'layouts')->delete();
    }
};
