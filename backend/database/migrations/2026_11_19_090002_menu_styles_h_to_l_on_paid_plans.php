<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menu styles H · Arabic, I · Islamic, J · Somali, K · Black & White and
 * L · Colourful (owner, 2026-11-18) are for Plus and up, like A, C and E–G
 * (QUESTIONS #229): the Free plan keeps its two styles. A plan without a row
 * uses the default (on), so only the Free plan gets "off" rows. Runs with the
 * code that adds the styles, so no restaurant on Free can have picked one
 * before (the Super Admin can change it per plan later).
 */
return new class extends Migration
{
    private const KEYS = ['style_arabic', 'style_islamic', 'style_somali', 'style_mono', 'style_colour'];

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
