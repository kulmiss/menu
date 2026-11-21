<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menu styles E · Mesob, F · Night Lounge and G · Street Board (owner,
 * 2026-11-18, QUESTIONS #228) are for Plus and up, like A and C: the Free plan
 * keeps its two styles. A plan without a row uses the default (on), so only
 * the Free plan gets "off" rows. Runs with the code that adds the styles, so no
 * restaurant on Free can have picked one before (the Super Admin can change it
 * per plan later).
 */
return new class extends Migration
{
    private const KEYS = ['style_mesob', 'style_lounge', 'style_board'];

    public function up(): void
    {
        $free = DB::table('plans')->where('slug', 'free')->value('id');
        if ($free === null) {
            return;
        }
        foreach (self::KEYS as $key) {
            DB::table('plan_features')->updateOrInsert(['plan_id' => $free, 'feature_key' => $key], ['enabled' => false, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        $free = DB::table('plans')->where('slug', 'free')->value('id');
        DB::table('plan_features')->where('plan_id', $free)->whereIn('feature_key', self::KEYS)->delete();
    }
};
