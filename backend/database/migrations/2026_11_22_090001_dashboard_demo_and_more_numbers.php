<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Super Admin dashboard (owner, 2026-11-22): every number with or without the
 * website demo restaurants, and more numbers from data the app already keeps
 * (no new guest tracking). The summary tables are rebuilt from the raw data by
 * `platform:aggregate`, so the old rows are simply removed here; the next run
 * (hourly, or by hand) fills 400 days again.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('platform_daily')->delete();
        Schema::table('platform_daily', function (Blueprint $table) {
            $table->dropPrimary(['plan_id', 'date']);
        });
        Schema::table('platform_daily', function (Blueprint $table) {
            // 0: real restaurants only; 1: with the demo restaurants.
            $table->boolean('demo')->default(false)->first();
            $table->unsignedInteger('deleted_restaurants')->default(0)->after('new_restaurants');
            // First guest view of a new restaurant's menu that day.
            $table->unsignedInteger('activated')->default(0)->after('deleted_restaurants');
            // "My order" lists shown to a waiter (counted on the guest's phone, no personal data).
            $table->unsignedInteger('order_lists')->default(0)->after('order_adds');
            $table->unsignedInteger('dishes_added')->default(0)->after('edits');
            $table->unsignedInteger('menu_changes')->default(0)->after('dishes_added');
            $table->unsignedInteger('upgrades')->default(0)->after('menu_changes');
            $table->unsignedInteger('downgrades')->default(0)->after('upgrades');
            $table->unsignedInteger('cancellations')->default(0)->after('downgrades');
            $table->primary(['demo', 'plan_id', 'date']);
        });

        Schema::table('tenant_health', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->after('plan_id')->index();
            $table->unsignedInteger('views_total')->default(0)->after('views_prev_30d');
            $table->boolean('chose_style')->default(false)->after('style');
            $table->boolean('uses_photos')->default(false)->after('chose_style');
            $table->boolean('uses_pdf')->default(false)->after('uses_photos');
            $table->boolean('uses_languages')->default(false)->after('uses_pdf');
            $table->string('locale', 10)->nullable()->after('uses_languages');
            // The city of the restaurant's time zone (addresses are free text).
            $table->string('place', 60)->nullable()->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_health', function (Blueprint $table) {
            $table->dropIndex(['is_demo']);
            $table->dropColumn(['is_demo', 'views_total', 'chose_style', 'uses_photos', 'uses_pdf', 'uses_languages', 'locale', 'place']);
        });
        DB::table('platform_daily')->delete();
        Schema::table('platform_daily', function (Blueprint $table) {
            $table->dropPrimary(['demo', 'plan_id', 'date']);
        });
        Schema::table('platform_daily', function (Blueprint $table) {
            $table->dropColumn(['demo', 'deleted_restaurants', 'activated', 'order_lists', 'dishes_added', 'menu_changes', 'upgrades', 'downgrades', 'cancellations']);
            $table->primary(['plan_id', 'date']);
        });
    }
};
