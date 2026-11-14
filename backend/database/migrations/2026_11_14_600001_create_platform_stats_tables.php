<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-computed numbers for the Super Admin dashboard (owner, 2026-11-13,
 * QUESTIONS #186). Built by `platform:aggregate` from data the app already
 * has; the dashboard reads only these tables, never the raw ones.
 */
return new class extends Migration
{
    public function up(): void
    {
        // One row per day and plan (plan_id 0 = all plans). Restaurants count under their current plan.
        Schema::create('platform_daily', function (Blueprint $table) {
            $table->date('date');
            $table->unsignedBigInteger('plan_id')->default(0);
            $table->unsignedInteger('restaurants')->default(0);
            $table->unsignedInteger('new_restaurants')->default(0);
            $table->unsignedInteger('paying')->default(0);
            $table->unsignedInteger('active')->default(0); // menu views or admin edits that day
            $table->unsignedBigInteger('revenue_minor')->default(0); // payments made that day, default currency
            $table->unsignedBigInteger('mrr_minor')->default(0);
            $table->unsignedInteger('menu_views')->default(0);
            $table->unsignedInteger('qr_scans')->default(0);
            $table->unsignedInteger('dish_views')->default(0);
            $table->unsignedInteger('order_adds')->default(0);
            $table->unsignedInteger('edits')->default(0);
            $table->primary(['plan_id', 'date']);
        });

        // One row per restaurant: activity windows, setup, usage and a health score.
        Schema::create('tenant_health', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->primary();
            $table->unsignedBigInteger('plan_id')->nullable()->index();
            $table->string('state', 20);
            $table->date('created_on');
            $table->boolean('confirmed')->default(true);
            $table->unsignedTinyInteger('setup_pct')->default(0);
            $table->unsignedInteger('dishes')->default(0);
            $table->unsignedInteger('views_7d')->default(0);
            $table->unsignedInteger('views_30d')->default(0);
            $table->unsignedInteger('views_90d')->default(0);
            $table->unsignedInteger('views_prev_30d')->default(0);
            $table->unsignedInteger('scans_30d')->default(0);
            $table->unsignedInteger('scans_90d')->default(0);
            $table->unsignedInteger('edits_7d')->default(0);
            $table->unsignedInteger('edits_30d')->default(0);
            $table->timestamp('last_edit_at')->nullable();
            $table->date('last_view_on')->nullable();
            $table->unsignedInteger('members')->default(0);
            $table->unsignedInteger('members_2fa')->default(0);
            $table->unsignedInteger('photos')->default(0);
            $table->unsignedInteger('photos_limit')->nullable();
            $table->unsignedInteger('branches')->default(0);
            $table->unsignedInteger('branches_limit')->nullable();
            $table->string('style', 10)->nullable();
            $table->boolean('uses_price_periods')->default(false);
            $table->boolean('uses_promotions')->default(false);
            $table->boolean('uses_order_list')->default(false);
            $table->boolean('uses_print')->default(false);
            $table->unsignedBigInteger('revenue_30d_minor')->default(0);
            $table->unsignedBigInteger('revenue_90d_minor')->default(0);
            $table->unsignedBigInteger('revenue_365d_minor')->default(0);
            $table->date('first_paid_on')->nullable();
            $table->date('paid_until')->nullable(); // end of the last paid period
            $table->unsignedTinyInteger('score')->default(0);
            $table->string('band', 10)->index(); // healthy | at_risk | inactive
            $table->timestamp('computed_at');
        });

        // Platform-wide numbers that aren't per day or per restaurant (admins, two-factor), per plan.
        Schema::create('platform_snapshot', function (Blueprint $table) {
            $table->string('key', 40)->primary();
            $table->json('value');
            $table->timestamp('computed_at');
        });

        // Range queries over all restaurants' statistics by date.
        Schema::table('stats_daily', function (Blueprint $table) {
            $table->index(['date', 'metric'], 'stats_daily_date_metric');
        });
    }

    public function down(): void
    {
        Schema::table('stats_daily', fn (Blueprint $table) => $table->dropIndex('stats_daily_date_metric'));
        Schema::dropIfExists('platform_snapshot');
        Schema::dropIfExists('tenant_health');
        Schema::dropIfExists('platform_daily');
    }
};
