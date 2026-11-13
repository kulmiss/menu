<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Self sign-up (owner, 2026-11-13): where a self-registered restaurant stands
 * (waiting for the e-mail check or for approval; null = live), and the Free
 * plan every sign-up gets. Runs on live too, so the plan always exists; the
 * Super Admin can edit it afterwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('signup_state', 20)->nullable()->after('status')->index();
        });

        if (DB::table('plans')->where('slug', 'free')->doesntExist()) {
            $id = DB::table('plans')->insertGetId([
                'name' => json_encode(['en' => 'Free']),
                'slug' => 'free',
                'price_minor' => 0,
                'currency' => 'ETB',
                'interval' => 'month',
                'trial_days' => 0,
                'limits' => json_encode(['branches' => 1, 'menus' => 2, 'items' => 30, 'photos' => 30, 'tables' => 10]),
                'is_public' => true,
                'is_active' => true,
                'sort' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $features = [
                'branches' => false, 'promotions' => false, 'insights' => false, 'import_export' => false,
                'remove_branding' => false, 'pdf_menu' => true, 'order_list' => true,
                'style_editorial' => false, 'style_grid' => true, 'style_bistro' => false, 'style_minimal' => true,
            ];
            foreach ($features as $key => $on) {
                DB::table('plan_features')->insert(['plan_id' => $id, 'feature_key' => $key, 'enabled' => $on, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('signup_state');
        });
        // The Free plan stays: restaurants may be on it.
    }
};
